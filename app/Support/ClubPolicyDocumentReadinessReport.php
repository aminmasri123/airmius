<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ClubPolicyDocumentReadinessReport
{
    public const CONTRACT = 'club-policy-document-rollout.v1';

    public const SURFACES = ['web_mobile', 'web_desktop', 'android', 'ios'];

    public const JOURNEYS = [
        'public_visibility',
        'member_read',
        'document_management',
        'protected_download',
        'contribution_rule_link',
        'historical_integrity',
    ];

    public function make(bool $withData = false, ?string $evidencePath = null): array
    {
        $checks = [$this->repositoryCheck(), $this->evidenceTemplateCheck()];
        $inventory = null;
        if ($withData) {
            [$dataChecks, $inventory] = $this->dataChecks();
            array_push($checks, ...$dataChecks);
        } else {
            $checks[] = $this->check('database.runtime', 'pending', 'Runtime data was not inspected. Run with --with-data against a reviewed environment.');
        }
        $checks[] = $this->evidenceCheck($evidencePath);
        $automatedChecksPassed = collect($checks)
            ->reject(fn (array $check) => $check['id'] === 'local.evidence'
                || ($check['id'] === 'database.runtime' && $check['status'] === 'pending'))
            ->every(fn (array $check) => $check['status'] !== 'fail');
        $evidencePassed = collect($checks)->firstWhere('id', 'local.evidence')['status'] === 'pass';

        return [
            'contract' => self::CONTRACT,
            'generated_at' => now()->toIso8601String(),
            'mode' => $withData ? 'runtime-read-only' : 'repository-only',
            'automated_checks_passed' => $automatedChecksPassed,
            'decision' => $automatedChecksPassed && $withData && $evidencePassed ? 'go' : 'no-go',
            'inventory' => $inventory,
            'checks' => $checks,
        ];
    }

    private function repositoryCheck(): array
    {
        $paths = [
            'database/migrations/2026_09_24_000025_create_club_policy_documents.php',
            'database/migrations/2026_09_24_000026_link_contribution_rules_to_policy_documents.php',
            'docs/CLUB_POLICY_DOCUMENTS.md',
            'docs/CLUB_POLICY_DOCUMENT_ROLLOUT.md',
            'resources/release/club_policy_document_evidence.template.json',
        ];
        $missing = array_values(array_filter($paths, fn (string $path) => ! is_file(base_path($path))));

        return $this->check(
            'repository.contract',
            $missing === [] ? 'pass' : 'fail',
            $missing === []
                ? 'Additive schema, protected historical links and rollout artifacts are present.'
                : count($missing).' required repository artifacts are missing.',
        );
    }

    private function evidenceTemplateCheck(): array
    {
        $data = $this->json(base_path('resources/release/club_policy_document_evidence.template.json'));
        $valid = is_array($data)
            && ($data['contract'] ?? null) === self::CONTRACT
            && array_keys($data['surfaces'] ?? []) === self::SURFACES
            && array_keys($data['journeys'] ?? []) === self::JOURNEYS;

        return $this->check(
            'repository.evidence_template',
            $valid ? 'pass' : 'fail',
            $valid ? 'The versioned browser and real-device evidence template is complete.' : 'The evidence template is missing or invalid.',
        );
    }

    private function dataChecks(): array
    {
        $required = [
            'club_policy_documents' => ['id', 'club_id', 'file_id', 'type', 'title', 'valid_from', 'valid_until'],
            'club_contribution_rules' => ['id', 'club_id', 'club_policy_document_id', 'valid_from', 'valid_until'],
            'files' => ['id', 'club_id', 'team_id', 'event_id'],
        ];
        $missing = [];
        try {
            foreach ($required as $table => $columns) {
                if (! Schema::hasTable($table)) {
                    $missing[] = $table;

                    continue;
                }
                foreach ($columns as $column) {
                    if (! Schema::hasColumn($table, $column)) {
                        $missing[] = $table.'.'.$column;
                    }
                }
            }
        } catch (Throwable) {
            return [[$this->check('database.runtime', 'fail', 'The configured database is unavailable; no runtime data was inspected.')], null];
        }
        if ($missing !== []) {
            return [[$this->check('database.schema', 'fail', count($missing).' required tables or columns are missing.')], null];
        }

        try {
            $invalidFiles = DB::table('club_policy_documents as documents')
                ->join('files', 'files.id', '=', 'documents.file_id')
                ->where(fn ($query) => $query
                    ->whereColumn('files.club_id', '!=', 'documents.club_id')
                    ->orWhereNotNull('files.team_id')
                    ->orWhereNotNull('files.event_id'))
                ->count();
            $invalidRuleLinks = DB::table('club_contribution_rules as rules')
                ->join('club_policy_documents as documents', 'documents.id', '=', 'rules.club_policy_document_id')
                ->whereNotNull('rules.club_policy_document_id')
                ->where(fn ($query) => $query
                    ->whereColumn('documents.club_id', '!=', 'rules.club_id')
                    ->orWhere('documents.type', '!=', 'contribution_model')
                    ->orWhereColumn('rules.valid_from', '<', 'documents.valid_from')
                    ->orWhere(fn ($period) => $period
                        ->whereNotNull('documents.valid_until')
                        ->where(fn ($end) => $end
                            ->whereNull('rules.valid_until')
                            ->orWhereColumn('rules.valid_until', '>', 'documents.valid_until'))))
                ->count();
            $overlaps = DB::table('club_policy_documents as a')
                ->join('club_policy_documents as b', function ($join) {
                    $join->on('a.club_id', '=', 'b.club_id')
                        ->on('a.type', '=', 'b.type')
                        ->on('a.title', '=', 'b.title')
                        ->whereColumn('a.id', '<', 'b.id')
                        ->whereRaw('a.valid_from <= COALESCE(b.valid_until, ?) ', ['9999-12-31'])
                        ->whereRaw('COALESCE(a.valid_until, ?) >= b.valid_from', ['9999-12-31']);
                })->count();
            $inventory = [
                'documents_by_type' => DB::table('club_policy_documents')->select('type', DB::raw('COUNT(*) as aggregate'))->groupBy('type')->pluck('aggregate', 'type')->map(fn ($value) => (int) $value)->all(),
                'linked_contribution_rules' => DB::table('club_contribution_rules')->whereNotNull('club_policy_document_id')->count(),
                'unlinked_contribution_rules' => DB::table('club_contribution_rules')->whereNull('club_policy_document_id')->count(),
                'invalid_file_links' => $invalidFiles,
                'invalid_rule_links' => $invalidRuleLinks,
                'overlapping_document_pairs' => $overlaps,
            ];
        } catch (Throwable) {
            return [[
                $this->check('database.schema', 'pass', 'All additive policy-document tables and nullable references are present.'),
                $this->check('database.runtime', 'fail', 'The read-only runtime audit could not complete.'),
            ], null];
        }

        return [[
            $this->check('database.schema', 'pass', 'All additive policy-document tables and nullable references are present.'),
            $this->check('database.file_references', $invalidFiles === 0 ? 'pass' : 'fail', $invalidFiles === 0 ? 'Every document uses an unscoped file from the same club.' : $invalidFiles.' invalid file references require correction.'),
            $this->check('database.rule_references', $invalidRuleLinks === 0 ? 'pass' : 'fail', $invalidRuleLinks === 0 ? 'Every contribution-rule link matches club, type and validity period.' : $invalidRuleLinks.' invalid contribution-rule links require correction.'),
            $this->check('database.document_overlap', $overlaps === 0 ? 'pass' : 'fail', $overlaps === 0 ? 'No versions of the same document overlap.' : $overlaps.' overlapping document pairs require correction.'),
            $this->check('database.historical_inventory', 'pass', $inventory['unlinked_contribution_rules'].' existing contribution rules remain explicitly unlinked; no historical rows were reinterpreted.'),
        ], $inventory];
    }

    private function evidenceCheck(?string $path): array
    {
        if (! $path) {
            return $this->check('local.evidence', 'pending', 'No reviewed migration, browser and real-device evidence file was supplied.');
        }
        $data = $this->json($path);
        $shape = is_array($data)
            && array_keys($data) === ['contract', 'status', 'migration', 'surfaces', 'journeys', 'approvals', 'evidence_references']
            && ($data['contract'] ?? null) === self::CONTRACT
            && array_keys($data['migration'] ?? []) === ['backup_verified', 'dry_run_passed', 'rollback_rehearsed']
            && array_keys($data['surfaces'] ?? []) === self::SURFACES
            && array_keys($data['journeys'] ?? []) === self::JOURNEYS
            && array_keys($data['approvals'] ?? []) === ['product', 'engineering'];
        $statuses = [...array_values($data['surfaces'] ?? []), ...array_values($data['journeys'] ?? [])];
        $passed = $shape
            && ($data['status'] ?? null) === 'passed'
            && collect($data['migration'] ?? [])->every(fn ($value) => $value === true)
            && collect($data['approvals'] ?? [])->every(fn ($value) => $value === true)
            && $statuses !== [] && collect($statuses)->every(fn ($value) => $value === 'passed')
            && $this->validReferences($data['evidence_references'] ?? []);

        return $this->check('local.evidence', $passed ? 'pass' : 'pending', $passed ? 'Reviewed migration, browser, Android, iOS and workflow evidence is complete.' : 'Reviewed migration, browser, Android, iOS or workflow evidence remains open.');
    }

    private function validReferences(mixed $references): bool
    {
        return is_array($references) && $references !== []
            && collect($references)->every(fn ($reference) => is_string($reference) && preg_match('/^[A-Z0-9][A-Z0-9._:-]{2,80}$/i', $reference) === 1);
    }

    private function json(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }
        try {
            $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

            return is_array($data) ? $data : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function check(string $id, string $status, string $detail): array
    {
        return compact('id', 'status', 'detail');
    }
}
