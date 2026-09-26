<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubDepartment;
use App\Models\ClubRoleDefinition;
use App\Models\Notification;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Support\ClubPermissions;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TeamMemberManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_owner_can_create_team_invite_member_accept_invitation_and_change_role(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create(['email' => 'team-recipient@example.test']);
        $club = Club::factory()->create([
            'owner_id' => $owner->id,
            'name' => 'Airmius Journey Club',
        ]);
        $owner->givePermissionTo(Permission::findOrCreate('team.create', 'web'));

        $this->actingAs($owner)
            ->post(route('auth.teams.store'), [
                'club_id' => $club->id,
                'name' => 'Morgenlauf Team',
                'sport_type' => 'running',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $team = Team::query()
            ->where('club_id', $club->id)
            ->where('name', 'Morgenlauf Team')
            ->firstOrFail();

        $this->assertDatabaseHas('team_user', [
            'team_id' => $team->id,
            'user_id' => $owner->id,
            'role' => TeamRoles::COACH,
        ]);

        $this->actingAs($owner)
            ->post(route('auth.teams.invite', $team), [
                'user_id' => $recipient->id,
                'role' => TeamRoles::PLAYER,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $invitation = TeamInvitation::query()
            ->where('team_id', $team->id)
            ->where('recipient_id', $recipient->id)
            ->firstOrFail();

        $this->actingAs($recipient)
            ->post(route('auth.team-invitations.accept', $invitation))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('team_invitations', [
            'id' => $invitation->id,
            'status' => 'accepted',
        ]);
        $this->assertDatabaseHas('team_user', [
            'team_id' => $team->id,
            'user_id' => $recipient->id,
            'role' => TeamRoles::PLAYER,
        ]);

        $this->actingAs($owner)
            ->put(route('auth.teams.members.update', [$team, $recipient]), [
                'role' => TeamRoles::CAPTAIN,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Teamrolle aktualisiert.');

        $this->assertDatabaseHas('team_user', [
            'team_id' => $team->id,
            'user_id' => $recipient->id,
            'role' => TeamRoles::CAPTAIN,
        ]);
    }

    public function test_web_club_owner_can_add_update_and_remove_team_member(): void
    {
        [$owner, $member, $club, $team] = $this->teamFixture();

        $this->actingAs($owner)
            ->post(route('auth.teams.members.store', $team), [
                'user_id' => $member->id,
                'role' => TeamRoles::PLAYER,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Mitglied wurde zum Team hinzugefügt.');

        $this->assertDatabaseHas('team_user', [
            'team_id' => $team->id,
            'user_id' => $member->id,
            'role' => TeamRoles::PLAYER,
        ]);

        $conversationId = DB::table('conversations')
            ->where('type', 'team')
            ->where('team_id', $team->id)
            ->value('id');

        $this->assertNotNull($conversationId);
        $this->assertDatabaseHas('conversation_users', [
            'conversation_id' => $conversationId,
            'user_id' => $member->id,
        ]);

        $this->actingAs($owner)
            ->put(route('auth.teams.members.update', [$team, $member]), [
                'role' => TeamRoles::CAPTAIN,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Teamrolle aktualisiert.');

        $this->assertDatabaseHas('team_user', [
            'team_id' => $team->id,
            'user_id' => $member->id,
            'role' => TeamRoles::CAPTAIN,
        ]);

        $this->actingAs($owner)
            ->delete(route('auth.teams.members.destroy', [$team, $member]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Mitglied entfernt.');

        $this->assertDatabaseMissing('team_user', [
            'team_id' => $team->id,
            'user_id' => $member->id,
        ]);
        $this->assertDatabaseMissing('conversation_users', [
            'conversation_id' => $conversationId,
            'user_id' => $member->id,
        ]);
    }

    public function test_api_club_owner_can_add_update_and_remove_team_member(): void
    {
        [$owner, $member, $club, $team] = $this->teamFixture();

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/teams/{$team->id}/members", [
            'user_id' => $member->id,
            'role' => TeamRoles::PLAYER,
        ])
            ->assertCreated()
            ->assertJsonPath('data.id', $team->id)
            ->assertJsonPath('data.can_manage_metadata', true)
            ->assertJsonPath('data.can_remove_members', true)
            ->assertJsonPath('data.users.0.id', $member->id)
            ->assertJsonPath('data.users.0.team_role', TeamRoles::PLAYER);

        $this->putJson("/api/v1/teams/{$team->id}/members/{$member->id}", [
            'role' => TeamRoles::TREASURER,
        ])
            ->assertOk()
            ->assertJsonPath('data.users.0.id', $member->id)
            ->assertJsonPath('data.users.0.team_role', TeamRoles::TREASURER);

        $this->deleteJson("/api/v1/teams/{$team->id}/members/{$member->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $team->id);

        $this->assertDatabaseMissing('team_user', [
            'team_id' => $team->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_direct_team_add_requires_existing_club_membership(): void
    {
        [$owner, , , $team] = $this->teamFixture();
        $outsideUser = User::factory()->create();

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/teams/{$team->id}/members", [
            'user_id' => $outsideUser->id,
            'role' => TeamRoles::PLAYER,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);

        $this->assertDatabaseMissing('team_user', [
            'team_id' => $team->id,
            'user_id' => $outsideUser->id,
        ]);
    }

    public function test_join_request_notification_opens_the_team_workspace(): void
    {
        [$owner, $member, $club, $team] = $this->teamFixture();
        $specialist = User::factory()->create();
        $blockedManager = User::factory()->create();
        $club->users()->attach([
            $specialist->id => [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
            ],
            $blockedManager->id => [
                'role' => 'manager', 'roles' => ['manager'], 'membership_status' => 'active',
                'permission_overrides' => [ClubPermissions::MEMBERS_APPROVE => false],
            ],
        ]);
        $approver = $this->configuredRole($club, 'join_notification_approver', [ClubPermissions::MEMBERS_APPROVE]);
        $this->assignRole($owner, $club, $specialist, $approver, 'team', $team->id);

        $this->actingAs($member)
            ->post(route('auth.teams.join-requests.store', $team))
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $joinRequest = $team->joinRequests()
            ->where('user_id', $member->id)
            ->firstOrFail();
        $notification = Notification::query()
            ->where('user_id', $owner->id)
            ->where('type', 'team.join_request')
            ->firstOrFail();

        $this->assertSame(route('auth.teams.index', [
            'team' => $team->id,
            'team_join_request' => $joinRequest->id,
        ]), data_get($notification->data, 'url'));
        $this->assertSame($team->id, data_get($notification->data, 'team_id'));
        $this->assertSame($joinRequest->id, data_get($notification->data, 'join_request_id'));
        $this->assertDatabaseHas('notifications', [
            'user_id' => $specialist->id,
            'type' => 'team.join_request',
        ]);
        $this->assertFalse(Notification::query()
            ->where('user_id', $blockedManager->id)
            ->where('type', 'team.join_request')
            ->exists());
    }

    public function test_training_attendance_stats_follow_scoped_member_view_permission(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $blockedManager = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $club->users()->attach([
            $viewer->id => [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
            ],
            $blockedManager->id => [
                'role' => 'manager', 'roles' => ['manager'], 'membership_status' => 'active',
                'permission_overrides' => [ClubPermissions::MEMBERS_VIEW => false],
            ],
        ]);
        $role = $this->configuredRole($club, 'attendance_viewer', [ClubPermissions::MEMBERS_VIEW]);
        $this->assignRole($owner, $club, $viewer, $role, 'team', $team->id);

        $this->actingAs($viewer)
            ->getJson(route('auth.teams.attendance-stats', $team))
            ->assertOk()
            ->assertJsonPath('data.members_total', 0);
        Sanctum::actingAs($viewer);
        $this->getJson("/api/v1/teams/{$team->id}/attendance-stats")
            ->assertOk()
            ->assertJsonPath('data.members_total', 0);

        $this->actingAs($blockedManager)
            ->getJson(route('auth.teams.attendance-stats', $team))
            ->assertForbidden();
        Sanctum::actingAs($blockedManager);
        $this->getJson("/api/v1/teams/{$team->id}/attendance-stats")
            ->assertForbidden();
    }

    public function test_explicit_manager_denials_close_all_team_management_actions(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $member = User::factory()->create();
        $candidate = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach([
            $manager->id => [
                'role' => 'manager', 'roles' => ['manager'], 'membership_status' => 'active',
                'permission_overrides' => [
                    ClubPermissions::TEAMS_EDIT => false,
                    ClubPermissions::TEAMS_DELETE => false,
                    ClubPermissions::MEMBERS_APPROVE => false,
                    ClubPermissions::MEMBERS_ROLES => false,
                    ClubPermissions::MEMBERS_DELETE => false,
                ],
            ],
            $member->id => ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active'],
            $candidate->id => ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active'],
        ]);
        $team = Team::factory()->create(['club_id' => $club->id, 'name' => 'Geschütztes Team']);
        $team->users()->attach($manager->id, ['role' => TeamRoles::CAPTAIN]);
        $team->users()->attach($member->id, ['role' => TeamRoles::PLAYER]);

        Sanctum::actingAs($manager);
        $this->putJson("/api/v1/teams/{$team->id}", [
            'name' => 'Nicht erlaubt',
        ])->assertForbidden();
        $this->postJson("/api/v1/teams/{$team->id}/members", [
            'user_id' => $candidate->id,
            'role' => TeamRoles::PLAYER,
        ])->assertForbidden();
        $this->putJson("/api/v1/teams/{$team->id}/members/{$member->id}", [
            'role' => TeamRoles::CAPTAIN,
        ])->assertForbidden();
        $this->deleteJson("/api/v1/teams/{$team->id}/members/{$member->id}")
            ->assertForbidden();
        $this->deleteJson("/api/v1/teams/{$team->id}")->assertForbidden();

        $this->assertDatabaseHas('teams', ['id' => $team->id, 'name' => 'Geschütztes Team']);
        $this->assertDatabaseHas('team_user', [
            'team_id' => $team->id,
            'user_id' => $member->id,
            'role' => TeamRoles::PLAYER,
        ]);
    }

    public function test_team_join_request_requires_a_different_authorized_approver(): void
    {
        $owner = User::factory()->create();
        $secondManager = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($secondManager->id, [
            'role' => 'manager', 'roles' => ['manager'], 'membership_status' => 'active',
        ]);
        $team = Team::factory()->create(['club_id' => $club->id]);

        $this->actingAs($owner)
            ->post(route('auth.teams.join-requests.store', $team))
            ->assertRedirect();
        $joinRequest = $team->joinRequests()->where('user_id', $owner->id)->firstOrFail();

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/teams/{$team->id}/join-requests/{$joinRequest->id}/approve")
            ->assertUnprocessable();
        $this->actingAs($owner)
            ->post(route('auth.team-join-requests.approve', $joinRequest))
            ->assertStatus(422);
        $this->assertDatabaseHas('team_join_requests', ['id' => $joinRequest->id, 'status' => 'pending']);

        Sanctum::actingAs($secondManager);
        $this->postJson("/api/v1/teams/{$team->id}/join-requests/{$joinRequest->id}/approve")
            ->assertOk();
        $this->assertDatabaseHas('team_join_requests', ['id' => $joinRequest->id, 'status' => 'accepted']);
        $this->assertDatabaseHas('team_user', ['team_id' => $team->id, 'user_id' => $owner->id]);
    }

    public function test_scoped_member_actions_are_separated_for_team_and_department(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $firstMember = User::factory()->create();
        $secondMember = User::factory()->create();
        $thirdMember = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $department = ClubDepartment::query()->create([
            'club_id' => $club->id, 'name' => 'Jugend', 'is_public' => false,
        ]);
        $otherDepartment = ClubDepartment::query()->create([
            'club_id' => $club->id, 'name' => 'Senioren', 'is_public' => false,
        ]);
        $team = Team::factory()->create(['club_id' => $club->id, 'club_department_id' => $department->id]);
        $departmentTeam = Team::factory()->create(['club_id' => $club->id, 'club_department_id' => $department->id]);
        $otherTeam = Team::factory()->create(['club_id' => $club->id, 'club_department_id' => $otherDepartment->id]);
        foreach ([$actor, $firstMember, $secondMember, $thirdMember] as $member) {
            $club->users()->attach($member->id, [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
            ]);
        }

        $approver = $this->configuredRole($club, 'team_member_approver', [ClubPermissions::MEMBERS_APPROVE]);
        $roleEditor = $this->configuredRole($club, 'team_role_editor', [ClubPermissions::MEMBERS_ROLES]);
        $remover = $this->configuredRole($club, 'team_member_remover', [ClubPermissions::MEMBERS_DELETE]);

        $this->assignRole($owner, $club, $actor, $approver, 'team', $team->id);
        Sanctum::actingAs($actor);
        $this->postJson("/api/v1/teams/{$team->id}/members", [
            'user_id' => $firstMember->id, 'role' => TeamRoles::PLAYER,
        ])->assertCreated()->assertJsonPath('data.can_manage_members', true)
            ->assertJsonPath('data.can_update_member_roles', false)
            ->assertJsonPath('data.can_remove_members', false);
        $this->putJson("/api/v1/teams/{$team->id}/members/{$firstMember->id}", [
            'role' => TeamRoles::CAPTAIN,
        ])->assertForbidden();
        $this->deleteJson("/api/v1/teams/{$team->id}/members/{$firstMember->id}")->assertForbidden();

        $this->assignRole($owner, $club, $actor, $roleEditor, 'team', $team->id);
        Sanctum::actingAs($actor);
        $this->putJson("/api/v1/teams/{$team->id}/members/{$firstMember->id}", [
            'role' => TeamRoles::CAPTAIN,
        ])->assertOk()->assertJsonPath('data.can_update_member_roles', true);
        $this->postJson("/api/v1/teams/{$team->id}/members", [
            'user_id' => $secondMember->id, 'role' => TeamRoles::PLAYER,
        ])->assertForbidden();

        $this->assignRole($owner, $club, $actor, $remover, 'team', $team->id);
        Sanctum::actingAs($actor);
        $this->putJson("/api/v1/teams/{$team->id}/members/{$firstMember->id}", [
            'role' => TeamRoles::PLAYER,
        ])->assertForbidden();
        $this->deleteJson("/api/v1/teams/{$team->id}/members/{$firstMember->id}")
            ->assertOk()->assertJsonPath('data.can_remove_members', true);

        $this->assignRole($owner, $club, $actor, $approver, 'department', $department->id);
        Sanctum::actingAs($actor);
        $this->postJson("/api/v1/teams/{$departmentTeam->id}/members", [
            'user_id' => $secondMember->id, 'role' => TeamRoles::PLAYER,
        ])->assertCreated();
        $this->postJson("/api/v1/teams/{$otherTeam->id}/members", [
            'user_id' => $thirdMember->id, 'role' => TeamRoles::PLAYER,
        ])->assertForbidden();
    }

    private function teamFixture(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create([
            'owner_id' => $owner->id,
            'name' => 'Airmius Team Club',
        ]);
        $team = Team::factory()->create([
            'club_id' => $club->id,
            'name' => 'MVP Team',
        ]);

        $club->users()->syncWithoutDetaching([
            $member->id => [
                'role' => 'member',
                'roles' => ['member'],
                'membership_status' => 'active',
                'joined_on' => now()->toDateString(),
            ],
        ]);

        return [$owner, $member, $club, $team];
    }

    private function configuredRole(Club $club, string $key, array $permissions): ClubRoleDefinition
    {
        return ClubRoleDefinition::query()->create([
            'club_id' => $club->id,
            'key' => $key,
            'name' => str_replace('_', ' ', ucfirst($key)),
            'permissions' => $permissions,
            'is_active' => true,
        ]);
    }

    private function assignRole(
        User $owner,
        Club $club,
        User $member,
        ClubRoleDefinition $role,
        string $scopeType,
        int $scopeId,
    ): void {
        Sanctum::actingAs($owner);
        $this->putJson("/api/v1/clubs/{$club->id}/members/{$member->id}/role-definitions", [
            'assignments' => [[
                'role_definition_id' => $role->id,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
            ]],
        ])->assertOk();
    }
}
