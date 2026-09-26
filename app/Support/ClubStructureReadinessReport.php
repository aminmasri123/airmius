<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ClubStructureReadinessReport
{
    public const CONTRACT = 'club-structure-rollout.v1';

    public const SURFACES = ['web_mobile', 'web_desktop', 'android', 'ios'];

    public const JOURNEYS = [
        'organization_management',
        'organization_visibility',
        'team_assignment',
        'governance_management',
        'governance_visibility',
        'responsibility_assignment',
        'tenant_isolation',
    ];

    public function make(bool $withData = false, ?string $evidencePath = null): array
    {
        $checks = [$this->repositoryCheck(), $this->evidenceTemplateCheck()];
        $inventory = null;
        if ($withData) {
            [$runtimeChecks, $inventory] = $this->dataChecks();
            array_push($checks, ...$runtimeChecks);
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
            'database/migrations/2026_09_24_000014_create_club_organization_structure.php',
            'database/migrations/2026_09_24_000015_create_club_governance_structure.php',
            'app/Http/Controllers/Api/V1/ClubOrganizationController.php',
            'app/Http/Controllers/Api/V1/ClubGovernanceController.php',
            'resources/js/Components/Clubs/ClubGovernanceSection.vue',
            'mobile/airmius_mobile/lib/screens/club_organization_screen.dart',
            'docs/CLUB_STRUCTURE_ROLLOUT.md',
            'resources/release/club_structure_evidence.template.json',
        ];
        $missing = array_values(array_filter($paths, fn (string $path) => ! is_file(base_path($path))));

        return $this->check(
            'repository.contract',
            $missing === [] ? 'pass' : 'fail',
            $missing === []
                ? 'Organization, governance, web, native and rollout artifacts are present.'
                : count($missing).' required repository artifacts are missing.',
        );
    }

    private function evidenceTemplateCheck(): array
    {
        $data = $this->json(base_path('resources/release/club_structure_evidence.template.json'));
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
            'club_departments' => ['id', 'club_id'],
            'club_locations' => ['id', 'club_id'],
            'club_training_groups' => ['id', 'club_id', 'club_department_id', 'club_location_id'],
            'teams' => ['id', 'club_id', 'club_department_id', 'club_location_id', 'club_training_group_id'],
            'club_governance_bodies' => ['id', 'club_id', 'type', 'starts_on', 'ends_on'],
            'club_governance_assignments' => ['id', 'club_id', 'club_governance_body_id', 'user_id', 'club_external_member_id', 'starts_on', 'ends_on'],
            'club_user' => ['club_id', 'user_id'],
            'club_external_members' => ['id', 'club_id'],
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
            $invalidGroups = $this->invalidTrainingGroups();
            $invalidTeams = $this->invalidTeams();
            $invalidBodies = DB::table('club_governance_bodies')
                ->where(fn ($query) => $query
                    ->whereNotIn('type', ['board', 'committee', 'working_group'])
                    ->orWhere(fn ($period) => $period
                        ->whereNotNull('starts_on')->whereNotNull('ends_on')->whereColumn('starts_on', '>', 'ends_on')))
                ->count();
            $invalidAssignments = $this->invalidAssignments();
            $inventory = [
                'departments' => DB::table('club_departments')->count(),
                'locations' => DB::table('club_locations')->count(),
                'training_groups' => DB::table('club_training_groups')->count(),
                'assigned_teams' => DB::table('teams')->where(fn ($query) => $query
                    ->whereNotNull('club_department_id')->orWhereNotNull('club_location_id')->orWhereNotNull('club_training_group_id'))->count(),
                'unassigned_teams' => DB::table('teams')->whereNull('club_department_id')->whereNull('club_location_id')->whereNull('club_training_group_id')->count(),
                'governance_bodies' => DB::table('club_governance_bodies')->count(),
                'governance_assignments' => DB::table('club_governance_assignments')->count(),
                'invalid_training_groups' => $invalidGroups,
                'invalid_team_assignments' => $invalidTeams,
                'invalid_governance_bodies' => $invalidBodies,
                'invalid_governance_assignments' => $invalidAssignments,
            ];
        } catch (Throwable) {
            return [[
                $this->check('database.schema', 'pass', 'All additive organization and governance tables are present.'),
                $this->check('database.runtime', 'fail', 'The read-only runtime audit could not complete.'),
            ], null];
        }

        return [[
            $this->check('database.schema', 'pass', 'All additive organization and governance tables are present.'),
            $this->check('database.organization_references', $invalidGroups + $invalidTeams === 0 ? 'pass' : 'fail',
                $invalidGroups + $invalidTeams === 0 ? 'Organization references are tenant-consistent.' : ($invalidGroups + $invalidTeams).' invalid organization references require correction.'),
            $this->check('database.governance_references', $invalidBodies + $invalidAssignments === 0 ? 'pass' : 'fail',
                $invalidBodies + $invalidAssignments === 0 ? 'Governance types, periods, people and tenant references are consistent.' : ($invalidBodies + $invalidAssignments).' invalid governance records require correction.'),
            $this->check('database.historical_inventory', 'pass', $inventory['unassigned_teams'].' teams remain explicitly unassigned; no historical records were inferred or changed.'),
        ], $inventory];
    }

    private function invalidTrainingGroups(): int
    {
        return DB::table('club_training_groups as groups')
            ->leftJoin('club_departments as departments', 'departments.id', '=', 'groups.club_department_id')
            ->leftJoin('club_locations as locations', 'locations.id', '=', 'groups.club_location_id')
            ->where(fn ($query) => $query
                ->where(fn ($reference) => $reference->whereNotNull('groups.club_department_id')->where(fn ($invalid) => $invalid->whereNull('departments.id')->orWhereColumn('departments.club_id', '!=', 'groups.club_id')))
                ->orWhere(fn ($reference) => $reference->whereNotNull('groups.club_location_id')->where(fn ($invalid) => $invalid->whereNull('locations.id')->orWhereColumn('locations.club_id', '!=', 'groups.club_id'))))
            ->count();
    }

    private function invalidTeams(): int
    {
        return DB::table('teams')
            ->leftJoin('club_departments as departments', 'departments.id', '=', 'teams.club_department_id')
            ->leftJoin('club_locations as locations', 'locations.id', '=', 'teams.club_location_id')
            ->leftJoin('club_training_groups as groups', 'groups.id', '=', 'teams.club_training_group_id')
            ->where(fn ($query) => $query
                ->where(fn ($reference) => $reference->whereNotNull('teams.club_department_id')->where(fn ($invalid) => $invalid->whereNull('departments.id')->orWhereColumn('departments.club_id', '!=', 'teams.club_id')))
                ->orWhere(fn ($reference) => $reference->whereNotNull('teams.club_location_id')->where(fn ($invalid) => $invalid->whereNull('locations.id')->orWhereColumn('locations.club_id', '!=', 'teams.club_id')))
                ->orWhere(fn ($reference) => $reference->whereNotNull('teams.club_training_group_id')->where(fn ($invalid) => $invalid
                    ->whereNull('groups.id')->orWhereColumn('groups.club_id', '!=', 'teams.club_id')
                    ->orWhereRaw('COALESCE(teams.club_department_id, 0) != COALESCE(groups.club_department_id, 0)')
                    ->orWhereRaw('COALESCE(teams.club_location_id, 0) != COALESCE(groups.club_location_id, 0)'))))
            ->count();
    }

    private function invalidAssignments(): int
    {
        return DB::table('club_governance_assignments as assignments')
            ->leftJoin('club_governance_bodies as bodies', 'bodies.id', '=', 'assignments.club_governance_body_id')
            ->leftJoin('club_user as memberships', function ($join) {
                $join->on('memberships.user_id', '=', 'assignments.user_id')
                    ->on('memberships.club_id', '=', 'assignments.club_id');
            })
            ->leftJoin('club_external_members as external', 'external.id', '=', 'assignments.club_external_member_id')
            ->where(fn ($query) => $query
                ->whereNull('bodies.id')
                ->orWhereColumn('bodies.club_id', '!=', 'assignments.club_id')
                ->orWhere(fn ($person) => $person
                    ->where(fn ($missing) => $missing->whereNull('assignments.user_id')->whereNull('assignments.club_external_member_id'))
                    ->orWhere(fn ($duplicate) => $duplicate->whereNotNull('assignments.user_id')->whereNotNull('assignments.club_external_member_id')))
                ->orWhere(fn ($internal) => $internal->whereNotNull('assignments.user_id')->whereNull('memberships.user_id'))
                ->orWhere(fn ($outside) => $outside->whereNotNull('assignments.club_external_member_id')->where(fn ($invalid) => $invalid->whereNull('external.id')->orWhereColumn('external.club_id', '!=', 'assignments.club_id')))
                ->orWhere(fn ($period) => $period->whereNotNull('assignments.starts_on')->whereNotNull('assignments.ends_on')->whereColumn('assignments.starts_on', '>', 'assignments.ends_on')))
            ->count();
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
