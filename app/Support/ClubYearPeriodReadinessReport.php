<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ClubYearPeriodReadinessReport
{
    public const CONTRACT = 'club-year-period-rollout.v1';

    public const SURFACES = ['web_mobile', 'web_desktop', 'android', 'ios'];

    public const JOURNEYS = ['period_management', 'period_reports', 'historical_unassigned', 'team_season_planning'];

    public function make(bool $withData = false, ?string $evidencePath = null): array
    {
        $checks = [
            $this->repositoryCheck(),
            $this->evidenceTemplateCheck(),
        ];
        $inventory = null;

        if ($withData) {
            [$dataChecks, $inventory] = $this->dataChecks();
            array_push($checks, ...$dataChecks);
        } else {
            $checks[] = $this->check(
                'database.runtime',
                'pending',
                'Runtime data was not inspected. Run with --with-data against a reviewed environment.',
            );
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
            'database/migrations/2026_09_24_000016_create_club_year_periods.php',
            'database/migrations/2026_09_24_000017_link_club_year_periods_to_operations.php',
            'database/migrations/2026_09_24_000018_link_club_finance_entries_to_business_years.php',
            'database/migrations/2026_09_24_000019_link_teams_to_sport_year_periods.php',
            'app/Services/ClubYearPeriodResolver.php',
            'app/Services/ClubYearPeriodReportService.php',
            'docs/CLUB_YEAR_PERIODS.md',
            'docs/CLUB_YEAR_PERIOD_ROLLOUT.md',
        ];
        $missing = array_values(array_filter($paths, fn (string $path) => ! is_file(base_path($path))));

        return $this->check(
            'repository.contract',
            $missing === [] ? 'pass' : 'fail',
            $missing === []
                ? 'Additive schema, frozen assignment, reporting, team planning and rollout documentation are present.'
                : count($missing).' required repository artifacts are missing.',
        );
    }

    private function evidenceTemplateCheck(): array
    {
        $path = base_path('resources/release/club_year_period_evidence.template.json');
        $data = $this->json($path);
        $valid = is_array($data)
            && ($data['contract'] ?? null) === self::CONTRACT
            && array_keys($data['surfaces'] ?? []) === self::SURFACES
            && array_keys($data['journeys'] ?? []) === self::JOURNEYS;

        return $this->check(
            'repository.evidence_template',
            $valid ? 'pass' : 'fail',
            $valid
                ? 'The versioned browser and real-device evidence template is structurally complete.'
                : 'The browser and real-device evidence template is missing or invalid.',
        );
    }

    private function dataChecks(): array
    {
        $required = [
            'club_year_periods' => ['id', 'club_id', 'type', 'starts_on', 'ends_on'],
            'invoices' => ['club_id', 'business_year_period_id', 'contribution_year_period_id'],
            'bank_transactions' => ['club_id', 'business_year_period_id'],
            'club_finance_entries' => ['club_id', 'business_year_period_id'],
            'events' => ['club_id', 'team_id', 'sport_year_period_id'],
            'teams' => ['club_id', 'sport_year_period_id'],
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
            return [[
                $this->check('database.runtime', 'fail', 'The configured database is unavailable; no runtime data was inspected.'),
            ], null];
        }
        if ($missing !== []) {
            return [[
                $this->check('database.schema', 'fail', count($missing).' required tables or columns are missing.'),
            ], null];
        }

        try {
            $invalid = $this->invalidReferenceCounts();
            $overlaps = DB::table('club_year_periods as a')
                ->join('club_year_periods as b', function ($join) {
                    $join->on('a.club_id', '=', 'b.club_id')
                        ->on('a.type', '=', 'b.type')
                        ->whereColumn('a.id', '<', 'b.id')
                        ->whereColumn('a.starts_on', '<=', 'b.ends_on')
                        ->whereColumn('a.ends_on', '>=', 'b.starts_on');
                })
                ->count();
            $inventory = $this->inventory();
        } catch (Throwable) {
            return [[
                $this->check('database.schema', 'pass', 'All additive period tables and nullable references are present.'),
                $this->check('database.runtime', 'fail', 'The read-only runtime audit could not complete.'),
            ], null];
        }

        $invalidTotal = array_sum($invalid);
        $unassignedTotal = array_sum($inventory['unassigned']);

        return [[
            $this->check('database.schema', 'pass', 'All additive period tables and nullable references are present.'),
            $this->check(
                'database.references',
                $invalidTotal === 0 ? 'pass' : 'fail',
                $invalidTotal === 0
                    ? 'All stored period references match their club and required period type.'
                    : $invalidTotal.' cross-club or wrong-type references require correction.',
            ),
            $this->check(
                'database.period_overlap',
                $overlaps === 0 ? 'pass' : 'fail',
                $overlaps === 0
                    ? 'No periods of the same type overlap within a club.'
                    : $overlaps.' overlapping period pairs require correction.',
            ),
            $this->check(
                'database.historical_inventory',
                'pass',
                $unassignedTotal.' historical records remain explicitly unassigned; no dates were reinterpreted and no rows were changed.',
            ),
        ], [
            'periods' => $inventory['periods'],
            'unassigned' => $inventory['unassigned'],
            'invalid_references' => $invalid,
            'overlapping_period_pairs' => $overlaps,
        ]];
    }

    private function invalidReferenceCounts(): array
    {
        return [
            'invoice_business' => $this->invalidCount('invoices', 'business_year_period_id', 'business'),
            'invoice_contribution' => $this->invalidCount('invoices', 'contribution_year_period_id', 'contribution'),
            'bank_business' => $this->invalidCount('bank_transactions', 'business_year_period_id', 'business'),
            'finance_entry_business' => $this->invalidCount('club_finance_entries', 'business_year_period_id', 'business'),
            'event_sport' => DB::table('events as records')
                ->leftJoin('teams', 'teams.id', '=', 'records.team_id')
                ->join('club_year_periods as periods', 'periods.id', '=', 'records.sport_year_period_id')
                ->whereNotNull('records.sport_year_period_id')
                ->where(function ($query) {
                    $query->where('periods.type', '!=', 'sport')
                        ->orWhereRaw('periods.club_id != COALESCE(records.club_id, teams.club_id)');
                })
                ->count(),
            'team_sport' => $this->invalidCount('teams', 'sport_year_period_id', 'sport'),
        ];
    }

    private function invalidCount(string $table, string $foreignKey, string $type): int
    {
        return DB::table($table.' as records')
            ->join('club_year_periods as periods', 'periods.id', '=', 'records.'.$foreignKey)
            ->whereNotNull('records.'.$foreignKey)
            ->where(fn ($query) => $query
                ->where('periods.type', '!=', $type)
                ->orWhereColumn('periods.club_id', '!=', 'records.club_id'))
            ->count();
    }

    private function inventory(): array
    {
        return [
            'periods' => DB::table('club_year_periods')
                ->select('type', DB::raw('COUNT(*) as aggregate'))
                ->groupBy('type')
                ->pluck('aggregate', 'type')
                ->map(fn ($value) => (int) $value)
                ->all(),
            'unassigned' => [
                'invoice_business' => DB::table('invoices')->whereNull('business_year_period_id')->count(),
                'invoice_contribution' => DB::table('invoices')
                    ->whereIn('source', ['recurring_contribution', 'membership_contribution'])
                    ->whereNull('contribution_year_period_id')
                    ->count(),
                'bank_business' => DB::table('bank_transactions')->whereNull('business_year_period_id')->count(),
                'finance_entry_business' => DB::table('club_finance_entries')->whereNull('business_year_period_id')->count(),
                'event_sport' => DB::table('events')->whereNull('sport_year_period_id')->count(),
                'team_sport' => DB::table('teams')->whereNull('sport_year_period_id')->count(),
            ],
        ];
    }

    private function evidenceCheck(?string $path): array
    {
        $path = $path ?: (string) config('airmius.club_year_period_evidence_path', '');
        if ($path === '') {
            return $this->check('local.evidence', 'pending', 'No reviewed browser and real-device evidence file was supplied.');
        }

        $data = $this->json($path);
        if (! is_array($data) || ($data['contract'] ?? null) !== self::CONTRACT) {
            return $this->check('local.evidence', 'fail', 'The supplied evidence file is unreadable or uses the wrong contract.');
        }

        $shapeIsValid = array_keys($data) === [
            'contract', 'status', 'migration', 'surfaces', 'journeys', 'approvals', 'evidence_references',
        ]
            && array_keys($data['migration'] ?? []) === ['backup_verified', 'dry_run_passed', 'rollback_rehearsed']
            && array_keys($data['surfaces'] ?? []) === self::SURFACES
            && array_keys($data['journeys'] ?? []) === self::JOURNEYS
            && array_keys($data['approvals'] ?? []) === ['product', 'engineering'];
        $statuses = [
            ...array_values($data['surfaces'] ?? []),
            ...array_values($data['journeys'] ?? []),
        ];
        $checksPassed = $shapeIsValid
            && ($data['status'] ?? null) === 'passed'
            && ($data['migration']['backup_verified'] ?? false) === true
            && ($data['migration']['dry_run_passed'] ?? false) === true
            && ($data['migration']['rollback_rehearsed'] ?? false) === true
            && ($data['approvals']['product'] ?? false) === true
            && ($data['approvals']['engineering'] ?? false) === true
            && $statuses !== []
            && collect($statuses)->every(fn ($status) => $status === 'passed')
            && $this->validReferences($data['evidence_references'] ?? []);

        return $this->check(
            'local.evidence',
            $checksPassed ? 'pass' : 'pending',
            $checksPassed
                ? 'Reviewed migration, browser, Android, iOS and workflow evidence is complete.'
                : 'Reviewed migration, browser, Android, iOS or workflow evidence remains open.',
        );
    }

    private function validReferences(mixed $references): bool
    {
        return is_array($references)
            && $references !== []
            && collect($references)->every(fn ($reference) => is_string($reference)
                && preg_match('/^[A-Z0-9][A-Z0-9._:-]{2,80}$/i', $reference) === 1);
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
