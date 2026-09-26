<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubDepartment;
use App\Models\ClubGovernanceAssignment;
use App\Models\ClubGovernanceBody;
use App\Models\ClubLocation;
use App\Models\ClubTrainingGroup;
use App\Models\Team;
use App\Models\User;
use App\Support\ClubStructureReadinessReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClubStructureReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_clean_runtime_audit_is_aggregate_read_only_and_requires_reviewed_evidence(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, ['role' => 'member', 'roles' => ['member']]);
        $department = ClubDepartment::query()->create(['club_id' => $club->id, 'name' => 'Sport', 'is_public' => true]);
        $location = ClubLocation::query()->create(['club_id' => $club->id, 'name' => 'Halle', 'country' => 'DE', 'is_public' => true]);
        $group = ClubTrainingGroup::query()->create([
            'club_id' => $club->id, 'club_department_id' => $department->id, 'club_location_id' => $location->id,
            'name' => 'Training', 'is_public' => false,
        ]);
        Team::factory()->create([
            'club_id' => $club->id, 'club_department_id' => $department->id,
            'club_location_id' => $location->id, 'club_training_group_id' => $group->id,
        ]);
        $body = ClubGovernanceBody::query()->create([
            'club_id' => $club->id, 'type' => 'board', 'name' => 'Vorstand', 'is_public' => true,
        ]);
        ClubGovernanceAssignment::query()->create([
            'club_id' => $club->id, 'club_governance_body_id' => $body->id, 'user_id' => $member->id,
            'position_title' => 'Vorsitz', 'is_public' => true,
        ]);
        $before = $this->counts();

        $report = app(ClubStructureReadinessReport::class)->make(true);

        $this->assertTrue($report['automated_checks_passed']);
        $this->assertSame('no-go', $report['decision']);
        $this->assertSame(1, $report['inventory']['assigned_teams']);
        $this->assertSame(0, $report['inventory']['unassigned_teams']);
        $this->assertSame($before, $this->counts());
        $this->artisan('airmius:audit-club-structure', ['--with-data' => true, '--strict' => true])->assertFailed();
    }

    public function test_runtime_audit_detects_cross_club_and_invalid_records_without_disclosing_content(): void
    {
        $first = Club::factory()->create(['owner_id' => User::factory()]);
        $second = Club::factory()->create(['owner_id' => User::factory()]);
        $member = User::factory()->create(['name' => 'Nicht ausgeben']);
        $first->users()->attach($member->id, ['role' => 'member', 'roles' => ['member']]);
        $firstDepartment = ClubDepartment::query()->create(['club_id' => $first->id, 'name' => 'Intern A', 'is_public' => false]);
        $foreignDepartment = ClubDepartment::query()->create(['club_id' => $second->id, 'name' => 'Intern B', 'is_public' => false]);
        $foreignLocation = ClubLocation::query()->create(['club_id' => $second->id, 'name' => 'Geheim', 'country' => 'DE', 'is_public' => false]);
        $group = ClubTrainingGroup::query()->create([
            'club_id' => $first->id, 'club_department_id' => $firstDepartment->id,
            'name' => 'Vertraulich', 'is_public' => false,
        ]);
        DB::table('club_training_groups')->where('id', $group->id)->update(['club_location_id' => $foreignLocation->id]);
        Team::factory()->create([
            'club_id' => $first->id, 'club_department_id' => $foreignDepartment->id,
            'club_training_group_id' => $group->id,
        ]);
        $body = ClubGovernanceBody::query()->create([
            'club_id' => $first->id, 'type' => 'board', 'name' => 'Geheimrat', 'is_public' => false,
        ]);
        DB::table('club_governance_bodies')->where('id', $body->id)->update([
            'type' => 'invalid', 'starts_on' => '2027-01-01', 'ends_on' => '2026-01-01',
        ]);
        $assignment = ClubGovernanceAssignment::query()->create([
            'club_id' => $first->id, 'club_governance_body_id' => $body->id, 'user_id' => $member->id,
            'position_title' => 'Vertrauliche Rolle', 'is_public' => false,
        ]);
        DB::table('club_governance_assignments')->where('id', $assignment->id)->update(['club_id' => $second->id]);

        $report = app(ClubStructureReadinessReport::class)->make(true);
        $encoded = json_encode($report, JSON_THROW_ON_ERROR);

        $this->assertFalse($report['automated_checks_passed']);
        $this->assertSame(1, $report['inventory']['invalid_training_groups']);
        $this->assertSame(1, $report['inventory']['invalid_team_assignments']);
        $this->assertSame(1, $report['inventory']['invalid_governance_bodies']);
        $this->assertSame(1, $report['inventory']['invalid_governance_assignments']);
        foreach (['Nicht ausgeben', 'Intern A', 'Intern B', 'Geheim', 'Vertraulich', 'Geheimrat'] as $secret) {
            $this->assertStringNotContainsString($secret, $encoded);
        }
        $this->assertStringNotContainsString('club_id', json_encode($report['inventory'], JSON_THROW_ON_ERROR));
    }

    public function test_strict_audit_accepts_only_complete_versioned_evidence(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'club-structure-evidence-');
        file_put_contents($path, json_encode([
            'contract' => ClubStructureReadinessReport::CONTRACT,
            'status' => 'passed',
            'migration' => ['backup_verified' => true, 'dry_run_passed' => true, 'rollback_rehearsed' => true],
            'surfaces' => array_fill_keys(ClubStructureReadinessReport::SURFACES, 'passed'),
            'journeys' => array_fill_keys(ClubStructureReadinessReport::JOURNEYS, 'passed'),
            'approvals' => ['product' => true, 'engineering' => true],
            'evidence_references' => ['STRUCTURE-QA-2026-09-25', 'DEVICE-RUN-02'],
        ], JSON_THROW_ON_ERROR));

        try {
            $report = app(ClubStructureReadinessReport::class)->make(true, $path);
            $this->assertSame('go', $report['decision']);
            $this->artisan('airmius:audit-club-structure', [
                '--with-data' => true, '--evidence' => $path, '--strict' => true,
            ])->assertSuccessful();

            file_put_contents($path, str_replace('DEVICE-RUN-02', 'https://private.test/report', (string) file_get_contents($path)));
            $this->assertSame('no-go', app(ClubStructureReadinessReport::class)->make(true, $path)['decision']);
        } finally {
            @unlink($path);
        }
    }

    private function counts(): array
    {
        return collect([
            'club_departments', 'club_locations', 'club_training_groups', 'teams',
            'club_governance_bodies', 'club_governance_assignments',
        ])->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()])->all();
    }
}
