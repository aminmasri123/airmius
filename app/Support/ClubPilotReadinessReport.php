<?php

namespace App\Support;

use App\Models\Club;
use App\Services\ClubOnboardingService;
use JsonException;

final class ClubPilotReadinessReport
{
    private const STATUSES = ['pending', 'passed', 'failed'];

    public function __construct(private readonly ClubOnboardingService $onboardingService) {}

    /** @return array<string, mixed> */
    public function make(bool $includeData = false): array
    {
        $registry = ClubPilotAcceptanceRegistry::definitions();
        $checks = [
            $this->contractCheck($registry),
            $this->artifactCheck($registry),
            $this->templateCheck($registry),
            $this->cohortDataCheck($registry, $includeData),
            $this->localEvidenceCheck($registry),
            $this->externalGateCheck($registry),
        ];
        $summary = array_fill_keys(['pass', 'pending', 'skipped', 'fail'], 0);
        foreach ($checks as $check) {
            $summary[$check['status']]++;
        }

        return [
            'contract' => ClubPilotAcceptanceRegistry::CONTRACT,
            'generated_at' => now()->utc()->toIso8601String(),
            'mode' => $includeData ? 'repository_and_aggregate_cohort' : 'repository',
            'decision' => $summary['fail'] === 0 && $summary['pending'] === 0 && $summary['skipped'] === 0 ? 'go' : 'no_go',
            'automated_checks_passed' => $summary['fail'] === 0,
            'pilot_evidence_complete' => collect($checks)->firstWhere('id', 'external.club_pilot')['status'] === 'pass',
            'summary' => $summary,
            'checks' => $checks,
            'privacy' => [
                'stores_personal_data' => false,
                'stores_secrets' => false,
                'outputs_club_identifiers' => false,
                'outputs_user_identifiers' => false,
                'evidence_alias_pattern' => 'pilot-01..pilot-05',
            ],
        ];
    }

    /** @param array<string, mixed> $registry */
    private function contractCheck(array $registry): array
    {
        $criticalJourneys = CriticalJourneyRegistry::definitions();
        $passes = ($registry['contract'] ?? null) === ClubPilotAcceptanceRegistry::CONTRACT
            && count($registry['metrics'] ?? []) === 6
            && count($registry['checkpoints'] ?? []) === 5
            && collect($registry['journeys'] ?? [])->every(static fn (string $journey): bool => isset($criticalJourneys[$journey]))
            && data_get($registry, 'cohort.minimum_clubs') === 3
            && data_get($registry, 'cohort.maximum_clubs') === 5;

        return $this->check(
            'repository.contract',
            $passes ? 'pass' : 'fail',
            $passes ? 'Cohort, three critical journeys, six outcome metrics, five checkpoints, RACI, and rollback triggers are defined.' : 'The club pilot contract is incomplete.',
        );
    }

    /** @param array<string, mixed> $registry */
    private function artifactCheck(array $registry): array
    {
        $files = [
            data_get($registry, 'rollback.runbook'),
            data_get($registry, 'evidence.template'),
            'config/airmius_pilot.php',
        ];
        foreach ($registry['guest_acceptance'] as $control) {
            $files[] = $control['source'];
            $files[] = $control['test'];
        }
        foreach ($registry['readiness_sources'] as $source) {
            $files[] = $source['source'];
        }
        $files = array_values(array_unique(array_filter($files, 'is_string')));
        $missing = array_values(array_filter($files, static fn (string $path): bool => ! is_file(base_path($path))));

        return $this->check(
            'repository.artifacts',
            $missing === [] ? 'pass' : 'fail',
            $missing === [] ? count($files).' onboarding, support, analytics, guest, rollback, and evidence artifacts are present.' : 'Missing: '.implode(', ', $missing),
        );
    }

    /** @param array<string, mixed> $registry */
    private function templateCheck(array $registry): array
    {
        $template = $this->json(base_path($registry['evidence']['template']));
        $expectedMetrics = collect($registry['metrics'])->pluck('key')->all();
        $actualMetrics = collect($template['data']['metrics'] ?? [])->pluck('key')->all();
        $errors = $template['errors'];

        if (($template['data']['contract'] ?? null) !== ClubPilotAcceptanceRegistry::CONTRACT) {
            $errors[] = 'contract';
        }
        if (($template['data']['release_version'] ?? null) !== ReleaseReadinessReport::VERSION) {
            $errors[] = 'release_version';
        }
        if ($actualMetrics !== $expectedMetrics) {
            $errors[] = 'metrics';
        }
        if ($this->containsForbiddenEvidenceKey($template['data'])) {
            $errors[] = 'personal_data_key';
        }

        return $this->check(
            'repository.evidence_template',
            $errors === [] ? 'pass' : 'fail',
            $errors === [] ? 'The version-bound template has metric parity and contains no direct-identifier fields.' : 'Template errors: '.implode(', ', array_unique($errors)),
        );
    }

    /** @param array<string, mixed> $registry */
    private function cohortDataCheck(array $registry, bool $includeData): array
    {
        if (! $includeData) {
            return $this->check('runtime.cohort', 'skipped', 'Run with --with-data in the pilot environment; no club identifiers are printed.');
        }

        if (config('airmius_pilot.enabled') !== true) {
            return $this->check('runtime.cohort', 'pending', 'PILOT_ENABLED must be explicitly enabled in the pilot environment.');
        }

        $ids = collect(config('airmius_pilot.club_ids', []))
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
        $minimum = (int) $registry['cohort']['minimum_clubs'];
        $maximum = (int) $registry['cohort']['maximum_clubs'];

        if ($ids->count() < $minimum || $ids->count() > $maximum) {
            return $this->check('runtime.cohort', 'fail', "Configured cohort size must be {$minimum}–{$maximum}; identifiers are not printed.");
        }

        $clubs = Club::query()->whereIn('id', $ids)->get();
        if ($clubs->count() !== $ids->count()) {
            return $this->check('runtime.cohort', 'fail', 'One or more configured pilot clubs do not exist; identifiers are not printed.');
        }

        $onboarding = $this->onboardingService->forClubs($clubs);
        $threshold = (int) config('airmius_pilot.minimum_onboarding_percent', 80);
        $ready = $onboarding->where('completion_percent', '>=', $threshold)->count();
        $verified = $clubs->where('verification_status', 'verified')->count();
        $average = (int) round((float) $onboarding->avg('completion_percent'));
        $passes = $ready === $clubs->count() && $verified === $clubs->count();

        return $this->check(
            'runtime.cohort',
            $passes ? 'pass' : 'pending',
            sprintf(
                '%d clubs configured and found; %d verified; %d at or above %d%% onboarding; aggregate average %d%%. No identifiers emitted.',
                $clubs->count(),
                $verified,
                $ready,
                $threshold,
                $average,
            ),
        );
    }

    /** @param array<string, mixed> $registry */
    private function localEvidenceCheck(array $registry): array
    {
        $path = (string) config('airmius_pilot.evidence_path');
        if ($path === '' || ! is_file($path)) {
            return $this->check('local.evidence', 'pending', 'No local pilot evidence file is present. Copy the template locally and keep direct identifiers out.');
        }

        $evidence = $this->json($path);
        $errors = $evidence['errors'];
        $data = $evidence['data'];
        $size = (int) data_get($data, 'cohort.size', 0);
        $aliases = data_get($data, 'cohort.aliases', []);
        $expectedMetrics = collect($registry['metrics'])->pluck('key')->all();
        $actualMetrics = collect($data['metrics'] ?? [])->pluck('key')->all();

        if (($data['contract'] ?? null) !== ClubPilotAcceptanceRegistry::CONTRACT) {
            $errors[] = 'contract';
        }
        if (($data['release_version'] ?? null) !== ReleaseReadinessReport::VERSION) {
            $errors[] = 'release_version';
        }
        if (! in_array($data['status'] ?? null, self::STATUSES, true)) {
            $errors[] = 'status';
        }
        if ($size < 3 || $size > 5 || ! is_array($aliases) || count($aliases) !== $size) {
            $errors[] = 'cohort';
        } elseif (count(array_unique($aliases)) !== $size || array_filter($aliases, static fn (mixed $alias): bool => ! is_string($alias) || preg_match('/^pilot-0[1-5]$/', $alias) !== 1) !== []) {
            $errors[] = 'aliases';
        }
        if ($actualMetrics !== $expectedMetrics) {
            $errors[] = 'metrics';
        }
        if ($this->containsForbiddenEvidenceKey($data)) {
            $errors[] = 'personal_data_key';
        }
        if (($data['status'] ?? null) === 'passed' && ! $this->passedEvidenceComplete($data)) {
            $errors[] = 'approval_evidence';
        }

        if ($errors !== []) {
            return $this->check('local.evidence', 'fail', 'Local evidence is invalid: '.implode(', ', array_unique($errors)).'. Values are not printed.');
        }

        return $this->check(
            'local.evidence',
            ($data['status'] ?? null) === 'passed' ? 'pass' : (($data['status'] ?? null) === 'failed' ? 'fail' : 'pending'),
            'Local version-bound evidence is structurally valid; values and aliases are not printed.',
        );
    }

    /** @param array<string, mixed> $registry */
    private function externalGateCheck(array $registry): array
    {
        $manifest = $this->json(base_path('resources/release/platform_release_gates.json'));
        $gate = collect($manifest['data']['gates'] ?? [])->firstWhere('id', $registry['evidence']['external_gate']);
        $status = data_get($gate, 'status', 'missing');
        $mapped = match ($status) {
            'passed', 'waived' => 'pass',
            'failed', 'missing' => 'fail',
            default => 'pending',
        };

        return $this->check(
            'external.club_pilot',
            $mapped,
            $mapped === 'pass' ? 'The owner-bound external pilot gate has approval evidence.' : 'The authoritative 3–5-club pilot gate remains open; repository checks cannot approve it.',
        );
    }

    /** @return array{data:array<string,mixed>,errors:array<int,string>} */
    private function json(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return ['data' => [], 'errors' => ['file_missing']];
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

            return is_array($data)
                ? ['data' => $data, 'errors' => []]
                : ['data' => [], 'errors' => ['root_not_object']];
        } catch (JsonException) {
            return ['data' => [], 'errors' => ['invalid_json']];
        }
    }

    /** @param array<string, mixed> $data */
    private function containsForbiddenEvidenceKey(array $data): bool
    {
        $forbidden = [
            'club_id', 'club_ids', 'club_name', 'name', 'email', 'phone', 'address',
            'user_id', 'member_id', 'member_number', 'ip', 'message', 'notes', 'free_text',
        ];

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(mb_strtolower($key), $forbidden, true)) {
                return true;
            }
            if (is_array($value) && $this->containsForbiddenEvidenceKey($value)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $data */
    private function passedEvidenceComplete(array $data): bool
    {
        $period = $data['period'] ?? [];
        $metrics = $data['metrics'] ?? [];
        $journeys = $data['journeys'] ?? [];
        $approvals = $data['approvals'] ?? [];
        $references = $data['evidence_references'] ?? [];

        return data_get($data, 'cohort.selection_criteria_confirmed') === true
            && is_array($period)
            && $period !== []
            && collect($period)->every(static fn (mixed $date): bool => is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1)
            && is_array($metrics)
            && collect($metrics)->every(static fn (mixed $metric): bool => is_array($metric)
                && is_numeric($metric['baseline'] ?? null)
                && is_numeric($metric['outcome'] ?? null)
                && ($metric['target_met'] ?? null) === true)
            && is_array($journeys)
            && $journeys !== []
            && collect($journeys)->every(static fn (mixed $status): bool => $status === 'passed')
            && data_get($data, 'rollback.rehearsed') === true
            && data_get($data, 'rollback.recovery_verified') === true
            && is_string(data_get($data, 'rollback.evidence_reference'))
            && filled(data_get($data, 'rollback.evidence_reference'))
            && data_get($data, 'support.owner_confirmed') === true
            && data_get($data, 'support.sla_reviewed') === true
            && data_get($data, 'support.open_critical_findings') === 0
            && is_array($approvals)
            && count($approvals) >= 4
            && collect($approvals)->every(static fn (mixed $approval): bool => is_string($approval) && filled($approval))
            && is_array($references)
            && $references !== []
            && collect($references)->every(static fn (mixed $reference): bool => is_string($reference) && filled($reference));
    }

    /** @return array{id:string,status:string,detail:string} */
    private function check(string $id, string $status, string $detail): array
    {
        return compact('id', 'status', 'detail');
    }
}
