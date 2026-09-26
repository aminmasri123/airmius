<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Models\ClubGovernanceAssignment;
use App\Models\ClubGovernanceBody;
use App\Models\ClubRoleAssignment;
use App\Models\ClubRoleDefinition;
use App\Models\User;
use App\Support\ClubPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubGovernanceStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_manages_board_and_internal_or_external_responsibilities(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['name' => 'Alex Vorstand']);
        $club = Club::factory()->create(['owner_id' => $owner->id, 'is_listed' => true]);
        $club->users()->attach($member->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        $external = ClubExternalMember::query()->create([
            'club_id' => $club->id, 'created_by' => $owner->id, 'name' => 'Robin Extern',
            'email' => 'robin@example.test', 'role' => 'member', 'membership_status' => 'active',
        ]);
        Sanctum::actingAs($owner);

        $body = $this->postJson("/api/v1/clubs/{$club->id}/governance/bodies", [
            'type' => 'board', 'name' => ' Vorstand ', 'description' => 'Leitung',
            'starts_on' => '2026-01-01', 'ends_on' => '2027-12-31', 'is_public' => true,
        ])->assertCreated()->assertJsonPath('data.name', 'Vorstand')->json('data');

        $this->postJson("/api/v1/clubs/{$club->id}/governance/bodies/{$body['id']}/assignments", [
            'user_id' => $member->id, 'position_title' => ' Vorsitz ',
            'responsibilities' => 'Strategie und Vertretung', 'starts_on' => '2026-01-01',
            'ends_on' => '2027-12-31', 'is_public' => true,
        ])->assertCreated()->assertJsonPath('data.person.name', 'Alex Vorstand');

        $this->postJson("/api/v1/clubs/{$club->id}/governance/bodies/{$body['id']}/assignments", [
            'club_external_member_id' => $external->id, 'position_title' => 'Beisitz',
            'responsibilities' => null, 'is_public' => false,
        ])->assertCreated()->assertJsonPath('data.person.name', 'Robin Extern');

        $this->getJson("/api/v1/clubs/{$club->id}/governance")
            ->assertOk()->assertJsonCount(1, 'data.bodies')->assertJsonCount(2, 'data.bodies.0.assignments')
            ->assertJsonCount(3, 'data.member_options')->assertJsonPath('data.can_manage', true)
            ->assertJsonPath('data.can_edit', true)->assertJsonPath('data.can_delete', true);

        $activity = Activity::query()->where('type', 'club.governance.assignment.created')->latest('id')->firstOrFail();
        $this->assertSame(['entity_type' => 'assignment'], $activity->data);
        $this->assertStringNotContainsString('Strategie', json_encode($activity->data));
    }

    public function test_public_and_member_views_respect_body_and_assignment_visibility(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['name' => 'Internes Mitglied']);
        $outsider = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id, 'is_listed' => true]);
        $club->users()->attach($member->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        $publicBody = ClubGovernanceBody::query()->create(['club_id' => $club->id, 'type' => 'board', 'name' => 'Vorstand', 'is_public' => true]);
        $privateBody = ClubGovernanceBody::query()->create(['club_id' => $club->id, 'type' => 'working_group', 'name' => 'Krisenteam', 'is_public' => false]);
        ClubGovernanceAssignment::query()->create([
            'club_id' => $club->id, 'club_governance_body_id' => $publicBody->id, 'user_id' => $member->id,
            'position_title' => 'Vorsitz', 'responsibilities' => 'Öffentliche Vertretung', 'is_public' => true,
        ]);
        ClubGovernanceAssignment::query()->create([
            'club_id' => $club->id, 'club_governance_body_id' => $publicBody->id, 'user_id' => $member->id,
            'position_title' => 'Interne Koordination', 'responsibilities' => 'Vertraulich', 'is_public' => false,
        ]);
        ClubGovernanceAssignment::query()->create([
            'club_id' => $club->id, 'club_governance_body_id' => $privateBody->id, 'user_id' => $member->id,
            'position_title' => 'Leitung', 'is_public' => false,
        ]);

        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/clubs/{$club->id}/governance")
            ->assertOk()->assertJsonCount(1, 'data.bodies')->assertJsonCount(1, 'data.bodies.0.assignments')
            ->assertJsonPath('data.bodies.0.assignments.0.position_title', 'Vorsitz')
            ->assertJsonMissingPath('data.bodies.0.assignments.0.user_id')
            ->assertJsonCount(0, 'data.member_options');

        Sanctum::actingAs($member);
        $this->getJson("/api/v1/clubs/{$club->id}/governance")
            ->assertOk()->assertJsonCount(2, 'data.bodies')->assertJsonCount(2, 'data.bodies.0.assignments')
            ->assertJsonPath('data.can_manage', false)->assertJsonPath('data.can_edit', false)
            ->assertJsonPath('data.can_delete', false)->assertJsonCount(0, 'data.member_options');

        $club->users()->updateExistingPivot($member->id, ['membership_status' => 'former']);
        $this->getJson("/api/v1/clubs/{$club->id}/governance")
            ->assertOk()->assertJsonCount(1, 'data.bodies')->assertJsonCount(1, 'data.bodies.0.assignments');
    }

    public function test_cross_club_people_and_nested_resources_are_rejected(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $foreignMember = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $foreign = Club::factory()->create(['owner_id' => User::factory()]);
        $club->users()->attach($member->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        $foreign->users()->attach($foreignMember->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        $body = ClubGovernanceBody::query()->create(['club_id' => $club->id, 'type' => 'committee', 'name' => 'Sportausschuss', 'is_public' => false]);
        $foreignBody = ClubGovernanceBody::query()->create(['club_id' => $foreign->id, 'type' => 'board', 'name' => 'Fremd', 'is_public' => false]);
        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/clubs/{$club->id}/governance/bodies/{$body->id}/assignments", [
            'user_id' => $foreignMember->id, 'position_title' => 'Leitung', 'is_public' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('user_id');

        $this->putJson("/api/v1/clubs/{$club->id}/governance/bodies/{$foreignBody->id}", [
            'type' => 'board', 'name' => 'Manipuliert', 'is_public' => false,
        ])->assertNotFound();

        Sanctum::actingAs($member);
        $this->postJson("/api/v1/clubs/{$club->id}/governance/bodies", [
            'type' => 'committee', 'name' => 'Nicht erlaubt', 'is_public' => false,
        ])->assertForbidden();
    }

    public function test_validation_and_delete_protection_preserve_responsibility_history(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/clubs/{$club->id}/governance/bodies", [
            'type' => 'invalid', 'name' => 'Test', 'starts_on' => '2027-01-01',
            'ends_on' => '2026-01-01', 'is_public' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors(['type', 'ends_on']);

        $body = ClubGovernanceBody::query()->create(['club_id' => $club->id, 'type' => 'working_group', 'name' => 'Projektgruppe', 'is_public' => false]);
        $assignment = ClubGovernanceAssignment::query()->create([
            'club_id' => $club->id, 'club_governance_body_id' => $body->id, 'user_id' => $member->id,
            'position_title' => 'Projektleitung', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'is_public' => false,
        ]);

        $this->deleteJson("/api/v1/clubs/{$club->id}/governance/bodies/{$body->id}")
            ->assertUnprocessable()->assertJsonValidationErrors('governance_body');
        $this->deleteJson("/api/v1/clubs/{$club->id}/governance/bodies/{$body->id}/assignments/{$assignment->id}")->assertOk();
        $this->deleteJson("/api/v1/clubs/{$club->id}/governance/bodies/{$body->id}")->assertOk();
        $this->assertDatabaseMissing('club_governance_bodies', ['id' => $body->id]);
    }

    public function test_governance_edit_and_delete_permissions_are_separated(): void
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
        $this->assign($club, $editor, $this->role($club, 'governance_editor', [ClubPermissions::GOVERNANCE_EDIT]), $owner);
        $this->assign($club, $deleter, $this->role($club, 'governance_deleter', [ClubPermissions::GOVERNANCE_DELETE]), $owner);
        $body = ClubGovernanceBody::query()->create([
            'club_id' => $club->id, 'type' => 'committee', 'name' => 'Sportausschuss', 'is_public' => false,
        ]);

        Sanctum::actingAs($editor);
        $this->getJson("/api/v1/clubs/{$club->id}/governance")
            ->assertOk()->assertJsonPath('data.can_edit', true)->assertJsonPath('data.can_delete', false);
        $this->putJson("/api/v1/clubs/{$club->id}/governance/bodies/{$body->id}", [
            'type' => 'committee', 'name' => 'Sport und Jugend', 'is_public' => false,
        ])->assertOk()->assertJsonPath('data.name', 'Sport und Jugend');
        $this->deleteJson("/api/v1/clubs/{$club->id}/governance/bodies/{$body->id}")->assertForbidden();

        Sanctum::actingAs($deleter);
        $this->getJson("/api/v1/clubs/{$club->id}/governance")
            ->assertOk()->assertJsonPath('data.can_edit', false)->assertJsonPath('data.can_delete', true)
            ->assertJsonCount(0, 'data.member_options');
        $this->postJson("/api/v1/clubs/{$club->id}/governance/bodies", [
            'type' => 'committee', 'name' => 'Nicht erlaubt', 'is_public' => false,
        ])->assertForbidden();
        $this->deleteJson("/api/v1/clubs/{$club->id}/governance/bodies/{$body->id}")->assertOk();
    }

    private function role(Club $club, string $key, array $permissions): ClubRoleDefinition
    {
        return ClubRoleDefinition::query()->create([
            'club_id' => $club->id, 'key' => $key, 'name' => str_replace('_', ' ', $key),
            'permissions' => $permissions, 'is_active' => true,
        ]);
    }

    private function assign(Club $club, User $user, ClubRoleDefinition $role, User $owner): void
    {
        ClubRoleAssignment::query()->create([
            'club_id' => $club->id, 'club_role_definition_id' => $role->id,
            'user_id' => $user->id, 'scope_type' => 'club', 'scope_key' => 'club',
            'assigned_by' => $owner->id,
        ]);
    }
}
