<?php

namespace App\Support;

use JsonException;
use Throwable;

final class ObservabilityReadinessReport
{
    private const EVIDENCE_STATUSES = ['pending', 'passed', 'failed'];

    private const REFERENCE_PATTERN = '/\A[A-Za-z0-9][A-Za-z0-9._-]{2,119}\z/';

    private const FORBIDDEN_EVIDENCE_KEYS = [
        'url', 'uri', 'host', 'hostname', 'endpoint', 'path', 'query', 'headers',
        'body', 'payload', 'response', 'request', 'trace', 'exception', 'message',
        'token', 'secret', 'password', 'credential', 'authorization', 'cookie',
        'email', 'phone', 'ip', 'user_id', 'device_id', 'club_id', 'order_id',
    ];

    public function __construct(private readonly OperationsMonitor $operationsMonitor) {}

    /** @return array<string, mixed> */
    public function make(bool $includeRuntime = false): array
    {
        $registry = ObservabilityAcceptanceRegistry::definitions();
        $checks = [
            $this->contractCheck($registry),
            $this->artifactCheck($registry),
            $this->schedulerCheck(),
            $this->runtimeCheck($includeRuntime),
            $this->localEvidenceCheck($registry),
            $this->externalGateCheck($registry),
        ];
        $summary = array_fill_keys(['pass', 'pending', 'skipped', 'fail'], 0);
        foreach ($checks as $check) {
            $summary[$check['status']]++;
        }

        return [
            'contract' => ObservabilityAcceptanceRegistry::CONTRACT,
            'generated_at' => now()->utc()->toIso8601String(),
            'mode' => $includeRuntime ? 'repository_and_runtime' : 'repository',
            'decision' => $summary['fail'] === 0 && $summary['pending'] === 0 && $summary['skipped'] === 0 ? 'go' : 'no_go',
            'automated_checks_passed' => $summary['fail'] === 0,
            'external_evidence_complete' => collect($checks)->firstWhere('id', 'external.observability')['status'] === 'pass',
            'summary' => $summary,
            'checks' => $checks,
            'privacy' => [
                'stores_personal_data' => false,
                'stores_secrets' => false,
                'stores_raw_urls' => false,
                'stores_request_or_response_data' => false,
                'stores_dashboard_urls' => false,
                'requires_consented_aggregate_rum' => true,
                'minimum_rum_group_size' => 5,
            ],
        ];
    }

    /** @param array<string, mixed> $registry */
    private function contractCheck(array $registry): array
    {
        $expected = [
            'availability', 'api_error_rate', 'request_latency', 'guest_core_web_vitals',
            'queue_health', 'webhook_delivery', 'mail_delivery', 'push_delivery',
            'backup_freshness',
        ];
        $signals = $registry['signals'] ?? [];
        $keys = is_array($signals) ? array_keys($signals) : [];
        $signalsValid = $keys === $expected
            && collect($signals)->every(static fn (mixed $signal): bool => is_array($signal)
                && filled($signal['owner'] ?? null)
                && in_array($signal['severity'] ?? null, ['critical', 'high'], true)
                && (int) ($signal['alert_within_minutes'] ?? 0) > 0
                && is_array($signal['objectives'] ?? null)
                && ($signal['objectives'] ?? []) !== []
                && is_array($signal['sources'] ?? null)
                && ($signal['sources'] ?? []) !== []
                && ($signal['stores_personal_data'] ?? true) === false);
        $guestCoverage = data_get($signals, 'guest_core_web_vitals.coverage', []);
        $guestValid = collect(['guest.vereine', 'guest.marketplace', 'guest.e-learning'])
            ->every(static fn (string $route): bool => in_array($route, $guestCoverage, true))
            && data_get($signals, 'guest_core_web_vitals.objectives.maximum_lcp_p75_ms') === 2500
            && data_get($signals, 'guest_core_web_vitals.objectives.maximum_inp_p75_ms') === 200
            && data_get($signals, 'guest_core_web_vitals.objectives.maximum_cls_p75') === 0.1;
        $passes = ($registry['contract'] ?? null) === ObservabilityAcceptanceRegistry::CONTRACT
            && (int) ($registry['minimum_evidence_window_hours'] ?? 0) >= 24
            && $signalsValid
            && $guestValid
            && collect($registry['data_policy'] ?? [])->every(static fn (mixed $value, string $key): bool => $key === 'rum_minimum_group_size' ? (int) $value >= 5 : is_bool($value));

        return $this->check(
            'repository.contract',
            $passes ? 'pass' : 'fail',
            $passes
                ? 'Nine owned SLO signals, alert deadlines, guest Web Vitals, consent/minimum-group rules, and privacy boundaries are versioned.'
                : 'The versioned observability/SLO contract is incomplete or unsafe.',
        );
    }

    /** @param array<string, mixed> $registry */
    private function artifactCheck(array $registry): array
    {
        $files = array_values(array_filter($registry['artifacts'] ?? [], 'is_string'));
        $missing = array_values(array_filter($files, static fn (string $path): bool => ! is_file(base_path($path))));

        return $this->check(
            'repository.artifacts',
            $missing === [] ? 'pass' : 'fail',
            $missing === []
                ? count($files).' telemetry, operations, guest, test, runbook, and evidence artifacts are present.'
                : 'Required observability artifacts are missing; only relative repository paths are reported: '.implode(', ', $missing),
        );
    }

    /** @return array{id:string,status:string,detail:string} */
    private function schedulerCheck(): array
    {
        $schedule = file_get_contents(base_path('routes/console.php'));
        $passes = is_string($schedule)
            && preg_match(
                "/Schedule::command\('airmius:monitor-operations'\)\s*->hourly\(\)\s*->withoutOverlapping\(\);/s",
                $schedule,
            ) === 1;

        return $this->check(
            'repository.scheduler',
            $passes ? 'pass' : 'fail',
            $passes
                ? 'The bounded operations monitor is scheduled hourly without overlap.'
                : 'The hourly non-overlapping operations monitor schedule is missing.',
        );
    }

    /** @return array{id:string,status:string,detail:string} */
    private function runtimeCheck(bool $includeRuntime): array
    {
        if (! $includeRuntime) {
            return $this->check('runtime.operations', 'skipped', 'Run with --with-runtime in staging; runtime values and failure details are not emitted.');
        }

        $requiredConfiguration = config('airmius_monitoring.performance.enabled') === true
            && config('airmius_monitoring.backup.enabled') === true
            && config('airmius_monitoring.mobile_push.enabled') === true
            && filled(config('airmius_monitoring.errors.external_dsn'))
            && filled(config('airmius_backup.disk'))
            && config('airmius_backup.disk') !== 'local'
            && ! in_array(config('queue.default'), ['sync', 'null'], true);
        if (! $requiredConfiguration) {
            return $this->check(
                'runtime.operations',
                'fail',
                'Staging runtime monitoring must enable performance, offsite backup, push, external error collection, and a worker-backed queue. Values are not emitted.',
            );
        }

        try {
            $result = $this->operationsMonitor->run();
            $failures = (int) data_get($result, 'summary.fail', 0);
            $warnings = (int) data_get($result, 'summary.warn', 0);
            $status = $failures > 0 ? 'fail' : ($warnings > 0 ? 'pending' : 'pass');

            return $this->check(
                'runtime.operations',
                $status,
                "The bounded staging monitor completed with {$failures} failures and {$warnings} warnings; check values and details are not copied into release evidence.",
            );
        } catch (Throwable) {
            return $this->check('runtime.operations', 'fail', 'The staging monitor could not complete; exception, connection, and provider details are not emitted.');
        }
    }

    /** @param array<string, mixed> $registry */
    private function localEvidenceCheck(array $registry): array
    {
        $path = (string) data_get($registry, 'evidence.local_path', '');
        if ($path === '' || ! is_file($path)) {
            return $this->check('local.evidence', 'pending', 'No local observability evidence is present. Copy the versioned template and store only non-sensitive artifact references.');
        }

        $evidence = $this->json($path);
        $data = $evidence['data'];
        $errors = $evidence['errors'];
        $expectedKeys = array_keys($registry['signals']);
        $signals = $data['signals'] ?? [];
        $actualKeys = is_array($signals) ? array_keys($signals) : [];

        if (($data['contract'] ?? null) !== ObservabilityAcceptanceRegistry::CONTRACT) {
            $errors[] = 'contract';
        }
        if (($data['release_version'] ?? null) !== ReleaseReadinessReport::VERSION) {
            $errors[] = 'release_version';
        }
        if (! in_array($data['status'] ?? null, self::EVIDENCE_STATUSES, true)) {
            $errors[] = 'status';
        }
        if ($actualKeys !== $expectedKeys) {
            $errors[] = 'signals';
        }
        if ($this->containsForbiddenEvidenceKey($data)) {
            $errors[] = 'forbidden_data_key';
        }
        if (($data['status'] ?? null) === 'passed' && ! $this->passedEvidenceComplete($data, $registry)) {
            $errors[] = 'approval_evidence';
        }

        if ($errors !== []) {
            return $this->check('local.evidence', 'fail', 'Local evidence is invalid: '.implode(', ', array_unique($errors)).'. Paths and values are not printed.');
        }

        $status = match ($data['status']) {
            'passed' => 'pass',
            'failed' => 'fail',
            default => 'pending',
        };

        return $this->check(
            'local.evidence',
            $status,
            'Local version-bound evidence is structurally valid; dashboard, alert, verification, and review references are not emitted.',
        );
    }

    /** @param array<string, mixed> $registry */
    private function externalGateCheck(array $registry): array
    {
        $manifest = $this->json((string) config('airmius_observability.platform_gate_path'));
        $gate = collect($manifest['data']['gates'] ?? [])->firstWhere('id', data_get($registry, 'evidence.external_gate'));
        $status = data_get($gate, 'status', 'missing');
        $mapped = match ($status) {
            'passed', 'waived' => 'pass',
            'failed', 'missing' => 'fail',
            default => 'pending',
        };

        return $this->check(
            'external.observability',
            $mapped,
            $mapped === 'pass'
                ? 'The authoritative owner-bound observability gate has reviewed evidence.'
                : 'The external dashboard and tested-alert gate remains open; repository or local evidence cannot approve it.',
        );
    }

    /** @param array<string, mixed> $data @param array<string, mixed> $registry */
    private function passedEvidenceComplete(array $data, array $registry): bool
    {
        if ((int) ($data['observation_window_hours'] ?? 0) < (int) $registry['minimum_evidence_window_hours']
            || ! $this->validReference($data['review_reference'] ?? null)) {
            return false;
        }

        foreach (array_keys($registry['signals']) as $key) {
            $signal = data_get($data, 'signals.'.$key);
            if (! is_array($signal)
                || ($signal['status'] ?? null) !== 'passed'
                || ! $this->validReference($signal['dashboard_reference'] ?? null)
                || ! $this->validReference($signal['alert_test_reference'] ?? null)
                || ! $this->validReference($signal['verification_reference'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function validReference(mixed $value): bool
    {
        return is_string($value) && preg_match(self::REFERENCE_PATTERN, $value) === 1;
    }

    /** @param array<string, mixed> $data */
    private function containsForbiddenEvidenceKey(array $data): bool
    {
        foreach ($data as $key => $value) {
            $normalized = strtolower((string) $key);
            if (in_array($normalized, self::FORBIDDEN_EVIDENCE_KEYS, true)
                || collect(self::FORBIDDEN_EVIDENCE_KEYS)->contains(static fn (string $forbidden): bool => str_ends_with($normalized, '_'.$forbidden))) {
                return true;
            }
            if (is_array($value) && $this->containsForbiddenEvidenceKey($value)) {
                return true;
            }
        }

        return false;
    }

    /** @return array{data:array<string,mixed>,errors:array<int,string>} */
    private function json(string $path): array
    {
        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
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

    /** @return array{id:string,status:string,detail:string} */
    private function check(string $id, string $status, string $detail): array
    {
        return compact('id', 'status', 'detail');
    }
}
