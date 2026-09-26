<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\ClubDepartment;
use App\Models\ClubLocation;
use App\Models\ClubRoleAssignment;
use App\Models\ClubRoleDefinition;
use App\Models\ClubTrainingGroup;
use App\Models\Team;
use App\Models\User;
use App\Support\ClubPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubOrganizationStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_manages_normalized_organization_and_audit_contains_no_content(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id, 'is_listed' => true]);
        Sanctum::actingAs($owner);

        $department = $this->postJson("/api/v1/clubs/{$club->id}/organization/departments", [
            'name' => ' Fußball ', 'sport_type' => 'Fußball', 'description' => 'Interne Planung', 'is_public' => true,
        ])->assertCreated()->assertJsonPath('data.name', 'Fußball')->json('data');

        $location = $this->postJson("/api/v1/clubs/{$club->id}/organization/locations", [
            'name' => ' Sportzentrum ', 'street' => 'Musterweg', 'house_number' => '1',
            'postal_code' => '50667', 'city' => 'Köln', 'country' => 'de', 'notes' => 'Halle 2', 'is_public' => true,
        ])->assertCreated()->assertJsonPath('data.country', 'DE')->json('data');

        $this->postJson("/api/v1/clubs/{$club->id}/organization/training-groups", [
            'name' => 'U16 Technik', 'club_department_id' => $department['id'],
            'club_location_id' => $location['id'], 'sport_type' => 'Fußball',
            'description' => 'Dienstags', 'is_public' => false,
        ])->assertCreated();

        $this->getJson("/api/v1/clubs/{$club->id}/organization")
            ->assertOk()
            ->assertJsonCount(1, 'data.departments')
            ->assertJsonCount(1, 'data.locations')
            ->assertJsonCount(1, 'data.training_groups')
            ->assertJsonPath('data.can_manage', true)
            ->assertJsonPath('data.can_edit', true)
            ->assertJsonPath('data.can_delete', true)
            ->assertJsonPath('data.can_edit_team_assignments', true);

        $activity = Activity::query()->where('type', 'club.organization.training_group.created')->firstOrFail();
        $this->assertSame(['entity_type' => 'training_group'], $activity->data);
        $this->assertStringNotContainsString('Dienstags', json_encode($activity->data));
    }

    public function test_public_view_is_filtered_while_member_sees_internal_units_without_write_access(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id, 'is_listed' => true]);
        $club->users()->attach($member->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        ClubDepartment::query()->create(['club_id' => $club->id, 'name' => 'Öffentlich', 'is_public' => true]);
        $internalDepartment = ClubDepartment::query()->create(['club_id' => $club->id, 'name' => 'Intern', 'is_public' => false]);
        ClubLocation::query()->create(['club_id' => $club->id, 'name' => 'Halle', 'country' => 'DE', 'notes' => 'Interner Schlüsselcode', 'is_public' => true]);
        Team::factory()->create([
            'club_id' => $club->id,
            'club_department_id' => $internalDepartment->id,
            'name' => 'Interne Mannschaft',
        ]);

        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/clubs/{$club->id}/organization")
            ->assertOk()->assertJsonCount(1, 'data.departments')->assertJsonMissing(['name' => 'Intern'])
            ->assertJsonCount(0, 'data.team_assignments')
            ->assertJsonMissingPath('data.locations.0.notes');

        Sanctum::actingAs($member);
        $this->getJson("/api/v1/clubs/{$club->id}/organization")
            ->assertOk()->assertJsonCount(2, 'data.departments')->assertJsonPath('data.can_manage', false)
            ->assertJsonPath('data.can_edit', false)
            ->assertJsonPath('data.can_delete', false)
            ->assertJsonPath('data.can_edit_team_assignments', false)
            ->assertJsonCount(1, 'data.team_assignments')
            ->assertJsonPath('data.team_assignments.0.club_department_id', $internalDepartment->id)
            ->assertJsonPath('data.locations.0.notes', 'Interner Schlüsselcode');
        $this->postJson("/api/v1/clubs/{$club->id}/organization/departments", [
            'name' => 'Nicht erlaubt', 'is_public' => false,
        ])->assertForbidden();

        $club->users()->updateExistingPivot($member->id, ['membership_status' => 'former']);
        $this->getJson("/api/v1/clubs/{$club->id}/organization")
            ->assertOk()
            ->assertJsonCount(1, 'data.departments')
            ->assertJsonCount(0, 'data.team_assignments')
            ->assertJsonMissingPath('data.locations.0.notes');
    }

    public function test_cross_club_and_inconsistent_assignments_are_rejected(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $foreign = Club::factory()->create(['owner_id' => User::factory()]);
        $department = ClubDepartment::query()->create(['club_id' => $club->id, 'name' => 'Laufen', 'is_public' => false]);
        $foreignLocation = ClubLocation::query()->create(['club_id' => $foreign->id, 'name' => 'Fremd', 'country' => 'DE', 'is_public' => false]);
        $group = ClubTrainingGroup::query()->create([
            'club_id' => $club->id, 'club_department_id' => $department->id,
            'name' => 'Sprint', 'is_public' => false,
        ]);
        $otherDepartment = ClubDepartment::query()->create(['club_id' => $club->id, 'name' => 'Schwimmen', 'is_public' => false]);
        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/clubs/{$club->id}/organization/training-groups", [
            'name' => 'Ungültig', 'club_location_id' => $foreignLocation->id, 'is_public' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('club_location_id');

        $team = Team::factory()->create(['club_id' => $club->id, 'name' => 'Erste']);
        $this->putJson("/api/v1/teams/{$team->id}", [
            'name' => $team->name, 'sport_type' => $team->sport_type,
            'club_training_group_id' => $group->id, 'club_department_id' => $otherDepartment->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('club_department_id');
    }

    public function test_team_assignment_is_audited_and_blocks_deletion_until_released(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $department = ClubDepartment::query()->create(['club_id' => $club->id, 'name' => 'Leichtathletik', 'is_public' => true]);
        $location = ClubLocation::query()->create(['club_id' => $club->id, 'name' => 'Stadion', 'country' => 'DE', 'is_public' => true]);
        $group = ClubTrainingGroup::query()->create([
            'club_id' => $club->id, 'club_department_id' => $department->id, 'club_location_id' => $location->id,
            'name' => 'Sprint', 'is_public' => true,
        ]);
        $team = Team::factory()->create(['club_id' => $club->id, 'name' => 'Staffel']);
        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/teams/{$team->id}", [
            'name' => $team->name, 'sport_type' => $team->sport_type, 'club_training_group_id' => $group->id,
        ])->assertOk()
            ->assertJsonPath('data.club_department_id', $department->id)
            ->assertJsonPath('data.club_location_id', $location->id)
            ->assertJsonPath('data.club_training_group_id', $group->id);

        $this->putJson("/api/v1/teams/{$team->id}", [
            'name' => 'Staffel A', 'sport_type' => $team->sport_type,
        ])->assertOk()->assertJsonPath('data.club_training_group_id', $group->id);

        $this->deleteJson("/api/v1/clubs/{$club->id}/organization/training-groups/{$group->id}")
            ->assertUnprocessable()->assertJsonValidationErrors('training_group');
        $this->deleteJson("/api/v1/clubs/{$club->id}/organization/departments/{$department->id}")
            ->assertUnprocessable()->assertJsonValidationErrors('department');

        $this->putJson("/api/v1/teams/{$team->id}", [
            'name' => $team->name, 'sport_type' => $team->sport_type,
            'club_department_id' => null, 'club_location_id' => null, 'club_training_group_id' => null,
        ])->assertOk();

        $this->deleteJson("/api/v1/clubs/{$club->id}/organization/training-groups/{$group->id}")->assertOk();
        $this->deleteJson("/api/v1/clubs/{$club->id}/organization/departments/{$department->id}")->assertOk();
        $this->deleteJson("/api/v1/clubs/{$club->id}/organization/locations/{$location->id}")->assertOk();

        $this->assertDatabaseHas('activities', ['club_id' => $club->id, 'team_id' => $team->id, 'type' => 'club.organization.team_assigned']);
    }

    public function test_organization_edit_delete_and_team_assignment_permissions_are_separated(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $deleter = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        foreach ([$editor, $deleter] as $member) {
            $club->users()->attach($member->id, [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
            ]);
        }
        $editRole = $this->role($club, 'organization_editor', [ClubPermissions::ORGANIZATION_EDIT]);
        $deleteRole = $this->role($club, 'organization_deleter', [ClubPermissions::ORGANIZATION_DELETE]);
        $this->assign($club, $editor, $editRole, $owner);
        $this->assign($club, $deleter, $deleteRole, $owner);
        $department = ClubDepartment::query()->create([
            'club_id' => $club->id, 'name' => 'Laufen', 'is_public' => false,
        ]);

        Sanctum::actingAs($editor);
        $this->getJson("/api/v1/clubs/{$club->id}/organization")
            ->assertOk()
            ->assertJsonPath('data.can_manage', true)
            ->assertJsonPath('data.can_edit', true)
            ->assertJsonPath('data.can_delete', false)
            ->assertJsonPath('data.can_edit_team_assignments', false);
        $this->putJson("/api/v1/clubs/{$club->id}/organization/departments/{$department->id}", [
            'name' => 'Laufsport', 'is_public' => false,
        ])->assertOk()->assertJsonPath('data.name', 'Laufsport');
        $this->deleteJson("/api/v1/clubs/{$club->id}/organization/departments/{$department->id}")
            ->assertForbidden();

        Sanctum::actingAs($deleter);
        $this->getJson("/api/v1/clubs/{$club->id}/organization")
            ->assertOk()
            ->assertJsonPath('data.can_edit', false)
            ->assertJsonPath('data.can_delete', true)
            ->assertJsonPath('data.can_edit_team_assignments', false);
        $this->postJson("/api/v1/clubs/{$club->id}/organization/departments", [
            'name' => 'Nicht erlaubt', 'is_public' => false,
        ])->assertForbidden();
        $this->deleteJson("/api/v1/clubs/{$club->id}/organization/departments/{$department->id}")
            ->assertOk();
    }

    public function test_department_scoped_organization_role_is_limited_to_its_structure(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id, 'is_listed' => false]);
        $club->users()->attach($editor->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
        ]);
        $department = ClubDepartment::query()->create([
            'club_id' => $club->id, 'name' => 'Jugend', 'is_public' => false,
        ]);
        $otherDepartment = ClubDepartment::query()->create([
            'club_id' => $club->id, 'name' => 'Erwachsene', 'is_public' => false,
        ]);
        $location = ClubLocation::query()->create([
            'club_id' => $club->id, 'name' => 'Jugendhalle', 'country' => 'DE',
            'notes' => 'Nur intern', 'is_public' => false,
        ]);
        $otherLocation = ClubLocation::query()->create([
            'club_id' => $club->id, 'name' => 'Hauptplatz', 'country' => 'DE', 'is_public' => false,
        ]);
        $group = ClubTrainingGroup::query()->create([
            'club_id' => $club->id, 'club_department_id' => $department->id,
            'club_location_id' => $location->id, 'name' => 'U16', 'is_public' => false,
        ]);
        $otherGroup = ClubTrainingGroup::query()->create([
            'club_id' => $club->id, 'club_department_id' => $otherDepartment->id,
            'club_location_id' => $otherLocation->id, 'name' => 'Herren', 'is_public' => false,
        ]);
        $team = Team::factory()->create([
            'club_id' => $club->id, 'club_department_id' => $department->id,
            'club_location_id' => $location->id, 'club_training_group_id' => $group->id,
        ]);
        Team::factory()->create([
            'club_id' => $club->id, 'club_department_id' => $otherDepartment->id,
            'club_location_id' => $otherLocation->id, 'club_training_group_id' => $otherGroup->id,
        ]);
        $role = $this->role($club, 'department_organization_manager', [
            ClubPermissions::ORGANIZATION_VIEW,
            ClubPermissions::ORGANIZATION_EDIT,
            ClubPermissions::ORGANIZATION_DELETE,
            ClubPermissions::TEAMS_EDIT,
        ]);
        $this->assign($club, $editor, $role, $owner, 'department', $department->id);

        Sanctum::actingAs($editor);
        $organization = $this->getJson("/api/v1/clubs/{$club->id}/organization")
            ->assertOk()
            ->assertJsonCount(2, 'data.departments')
            ->assertJsonCount(2, 'data.locations')
            ->assertJsonCount(2, 'data.training_groups')
            ->assertJsonCount(2, 'data.team_assignments')
            ->assertJsonPath('data.can_edit', true)
            ->assertJsonPath('data.can_delete', true)
            ->assertJsonPath('data.can_create_departments', false)
            ->assertJsonPath('data.can_create_locations', false)
            ->assertJsonPath('data.can_create_training_groups', true)
            ->assertJsonPath('data.can_edit_team_assignments', true)
            ->assertJsonPath('data.can_assign_teams_globally', false)
            ->json('data');
        $departments = collect($organization['departments'])->keyBy('id');
        $locations = collect($organization['locations'])->keyBy('id');
        $groups = collect($organization['training_groups'])->keyBy('id');
        $teams = collect($organization['team_assignments'])->keyBy('id');
        $this->assertTrue($departments[$department->id]['can_edit']);
        $this->assertTrue($departments[$department->id]['can_delete']);
        $this->assertTrue($departments[$department->id]['can_assign_teams']);
        $this->assertFalse($departments[$otherDepartment->id]['can_edit']);
        $this->assertFalse($departments[$otherDepartment->id]['can_delete']);
        $this->assertFalse($departments[$otherDepartment->id]['can_assign_teams']);
        $this->assertFalse($locations[$location->id]['can_edit']);
        $this->assertFalse($locations[$otherLocation->id]['can_edit']);
        $this->assertTrue($groups[$group->id]['can_edit']);
        $this->assertTrue($groups[$group->id]['can_assign_teams']);
        $this->assertFalse($groups[$otherGroup->id]['can_edit']);
        $this->assertFalse($groups[$otherGroup->id]['can_assign_teams']);
        $this->assertTrue($teams[$team->id]['can_edit_assignment']);
        $this->assertSame(1, $teams->where('can_edit_assignment', true)->count());

        $this->putJson("/api/v1/clubs/{$club->id}/organization/departments/{$department->id}", [
            'name' => 'Nachwuchs', 'is_public' => false,
        ])->assertOk()->assertJsonPath('data.name', 'Nachwuchs');
        $this->putJson("/api/v1/clubs/{$club->id}/organization/departments/{$otherDepartment->id}", [
            'name' => 'Nicht erlaubt', 'is_public' => false,
        ])->assertForbidden();
        $this->postJson("/api/v1/clubs/{$club->id}/organization/departments", [
            'name' => 'Neue Abteilung', 'is_public' => false,
        ])->assertForbidden();
        $this->postJson("/api/v1/clubs/{$club->id}/organization/locations", [
            'name' => 'Neuer Standort', 'country' => 'DE', 'is_public' => false,
        ])->assertForbidden();

        $createdGroup = $this->postJson("/api/v1/clubs/{$club->id}/organization/training-groups", [
            'name' => 'U14', 'club_department_id' => $department->id, 'is_public' => false,
        ])->assertCreated()->assertJsonPath('data.can_edit', true)->json('data');
        $this->postJson("/api/v1/clubs/{$club->id}/organization/training-groups", [
            'name' => 'Fremde Gruppe', 'club_department_id' => $otherDepartment->id, 'is_public' => false,
        ])->assertForbidden();
        $this->putJson("/api/v1/clubs/{$club->id}/organization/training-groups/{$group->id}", [
            'name' => 'Verschoben', 'club_department_id' => $otherDepartment->id, 'is_public' => false,
        ])->assertForbidden();
        $this->assertDatabaseHas('club_training_groups', [
            'id' => $group->id, 'club_department_id' => $department->id, 'name' => 'U16',
        ]);
        $this->deleteJson("/api/v1/clubs/{$club->id}/organization/training-groups/{$createdGroup['id']}")
            ->assertOk();

        $webSource = file_get_contents(resource_path('js/Pages/Auth/Dashboard/Clubs/Profile.vue'));
        $mobileSource = file_get_contents(base_path('mobile/airmius_mobile/lib/screens/club_organization_screen.dart'));
        $this->assertStringContainsString('canEditOrganizationItem(item)', $webSource);
        $this->assertStringContainsString('editableTeamAssignments', $webSource);
        $this->assertStringContainsString('_canEditItem(item)', $mobileSource);
        $this->assertStringContainsString('for (final team in _editableTeams)', $mobileSource);
    }

    private function role(Club $club, string $key, array $permissions): ClubRoleDefinition
    {
        return ClubRoleDefinition::query()->create([
            'club_id' => $club->id,
            'key' => $key,
            'name' => str_replace('_', ' ', $key),
            'permissions' => $permissions,
            'is_active' => true,
        ]);
    }

    private function assign(
        Club $club,
        User $user,
        ClubRoleDefinition $role,
        User $owner,
        string $scopeType = 'club',
        ?int $scopeId = null,
    ): void {
        ClubRoleAssignment::query()->create([
            'club_id' => $club->id,
            'club_role_definition_id' => $role->id,
            'user_id' => $user->id,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'scope_key' => $scopeType === 'club' ? 'club' : $scopeType.':'.$scopeId,
            'assigned_by' => $owner->id,
        ]);
    }
}
