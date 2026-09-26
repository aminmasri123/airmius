<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\ClubDepartment;
use App\Models\ClubLocation;
use App\Models\ClubRoleAssignment;
use App\Models\ClubRoleDefinition;
use App\Models\ClubTrainingGroup;
use App\Models\ClubYearPeriod;
use App\Models\Team;
use App\Models\User;
use App\Support\ClubPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubStructuralModelContractRegressionTest extends TestCase
{
    use RefreshDatabase;

    private const REQUIREMENTS = [
        'R1_TENANT_BOUNDARIES',
        'R2_HIERARCHY_CONSISTENCY',
        'R3_SEPARATED_ACTION_PERMISSIONS',
        'R4_SCOPED_ROLES_FAIL_CLOSED',
        'R5_VISIBILITY_MINIMIZATION',
        'R6_SEASON_PLANNING_NO_RETROFIT',
        'R7_REFERENTIAL_DELETE_PROTECTION',
        'R8_MINIMIZED_AUDIT_EVIDENCE',
    ];

    public function test_structural_models_satisfy_t012_t017_contract_requirements_together(): void
    {
        $owner = User::factory()->create();
        $scopedEditor = User::factory()->create();
        $outsider = User::factory()->create();
        $formerMember = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id, 'is_listed' => true]);
        $foreignClub = Club::factory()->create(['owner_id' => User::factory()->create()->id, 'is_listed' => true]);
        foreach ([$scopedEditor, $formerMember] as $member) {
            $club->users()->attach($member->id, [
                'role' => 'member',
                'roles' => ['member'],
                'membership_status' => $member->is($formerMember) ? 'former' : 'active',
            ]);
        }

        $department = ClubDepartment::query()->create([
            'club_id' => $club->id,
            'name' => 'Jugend',
            'sport_type' => 'football',
            'description' => 'Interne Jugendplanung',
            'is_public' => false,
        ]);
        $otherDepartment = ClubDepartment::query()->create([
            'club_id' => $club->id,
            'name' => 'Senioren',
            'is_public' => false,
        ]);
        $location = ClubLocation::query()->create([
            'club_id' => $club->id,
            'name' => 'Trainingszentrum',
            'country' => 'DE',
            'notes' => 'Schluesselcode 1234',
            'is_public' => true,
        ]);
        $sportYear = ClubYearPeriod::query()->create([
            'club_id' => $club->id,
            'type' => 'sport',
            'name' => 'Saison 2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);
        $foreignSportYear = ClubYearPeriod::query()->create([
            'club_id' => $foreignClub->id,
            'type' => 'sport',
            'name' => 'Fremde Saison',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);
        $group = ClubTrainingGroup::query()->create([
            'club_id' => $club->id,
            'club_department_id' => $department->id,
            'club_location_id' => $location->id,
            'sport_year_period_id' => $sportYear->id,
            'name' => 'U16 Aufbau',
            'description' => 'Belastungsnotizen intern',
            'is_public' => false,
        ]);
        $team = Team::factory()->create([
            'club_id' => $club->id,
            'club_department_id' => $department->id,
            'club_location_id' => $location->id,
            'club_training_group_id' => $group->id,
            'sport_year_period_id' => $sportYear->id,
        ]);
        $otherTeam = Team::factory()->create([
            'club_id' => $club->id,
            'club_department_id' => $otherDepartment->id,
        ]);
        $role = ClubRoleDefinition::query()->create([
            'club_id' => $club->id,
            'key' => 'department_structure_editor',
            'name' => 'Department structure editor',
            'permissions' => [
                ClubPermissions::ORGANIZATION_VIEW,
                ClubPermissions::ORGANIZATION_EDIT,
                ClubPermissions::TEAMS_EDIT,
            ],
            'is_active' => true,
        ]);
        ClubRoleAssignment::query()->create([
            'club_id' => $club->id,
            'club_role_definition_id' => $role->id,
            'user_id' => $scopedEditor->id,
            'scope_type' => 'department',
            'scope_id' => $department->id,
            'scope_key' => 'department:'.$department->id,
            'assigned_by' => $owner->id,
        ]);

        $this->assertSame([
            'R1_TENANT_BOUNDARIES',
            'R2_HIERARCHY_CONSISTENCY',
            'R3_SEPARATED_ACTION_PERMISSIONS',
            'R4_SCOPED_ROLES_FAIL_CLOSED',
            'R5_VISIBILITY_MINIMIZATION',
            'R6_SEASON_PLANNING_NO_RETROFIT',
            'R7_REFERENTIAL_DELETE_PROTECTION',
            'R8_MINIMIZED_AUDIT_EVIDENCE',
        ], self::REQUIREMENTS);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/clubs/{$club->id}/organization/training-groups", [
            'name' => 'Fremde Saison',
            'club_department_id' => $department->id,
            'club_location_id' => $location->id,
            'sport_year_period_id' => $foreignSportYear->id,
            'is_public' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('sport_year_period_id');
        $this->putJson("/api/v1/teams/{$team->id}", [
            'name' => $team->name,
            'sport_type' => $team->sport_type,
            'club_training_group_id' => $group->id,
            'club_department_id' => $otherDepartment->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('club_department_id');

        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/clubs/{$club->id}/organization")
            ->assertOk()
            ->assertJsonCount(0, 'data.departments')
            ->assertJsonCount(0, 'data.training_groups')
            ->assertJsonCount(0, 'data.team_assignments')
            ->assertJsonMissingPath('data.locations.0.notes');

        Sanctum::actingAs($formerMember);
        $this->getJson("/api/v1/clubs/{$club->id}/organization")
            ->assertOk()
            ->assertJsonCount(0, 'data.team_assignments')
            ->assertJsonMissingPath('data.locations.0.notes');

        Sanctum::actingAs($scopedEditor);
        $organization = $this->getJson("/api/v1/clubs/{$club->id}/organization")
            ->assertOk()
            ->assertJsonPath('data.can_edit', true)
            ->assertJsonPath('data.can_delete', false)
            ->assertJsonPath('data.can_assign_teams_globally', false)
            ->json('data');
        $teams = collect($organization['team_assignments'])->keyBy('id');
        $this->assertTrue($teams[$team->id]['can_edit_assignment']);
        if ($teams->has($otherTeam->id)) {
            $this->assertFalse($teams[$otherTeam->id]['can_edit_assignment']);
        }
        $this->assertSame($sportYear->id, $teams[$team->id]['sport_year_period_id']);

        $this->putJson("/api/v1/teams/{$otherTeam->id}", [
            'name' => 'Nicht erlaubt',
            'sport_type' => $otherTeam->sport_type,
        ])->assertForbidden();

        Sanctum::actingAs($owner);
        $this->putJson("/api/v1/teams/{$team->id}", [
            'name' => $team->name,
            'sport_type' => $team->sport_type,
            'club_training_group_id' => null,
            'club_department_id' => null,
            'club_location_id' => null,
            'sport_year_period_id' => $sportYear->id,
        ])->assertOk();

        $this->deleteJson("/api/v1/clubs/{$club->id}/year-periods/{$sportYear->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('year_period');
        $this->deleteJson("/api/v1/clubs/{$club->id}/organization/training-groups/{$group->id}")
            ->assertOk();

        $activity = Activity::query()
            ->where('club_id', $club->id)
            ->where('type', 'club.organization.training_group.deleted')
            ->firstOrFail();
        $encodedAudit = json_encode($activity->data);
        $this->assertSame(['entity_type' => 'training_group'], $activity->data);
        $this->assertStringNotContainsString('Belastungsnotizen intern', $encodedAudit);
        $this->assertStringNotContainsString('Schluesselcode', $encodedAudit);
    }
}
