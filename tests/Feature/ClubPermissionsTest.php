<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubPermissionDelegation;
use App\Models\ClubRoleAssignment;
use App\Models\ClubRoleDefinition;
use App\Models\User;
use App\Support\ClubPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_club_manager_can_manage_members_and_view_but_not_change_finance_by_default(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($manager->id, [
            'role' => 'manager',
            'roles' => ['manager'],
            'membership_status' => 'active',
        ]);

        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/clubs/{$club->id}")
            ->assertOk()
            ->assertJsonPath('data.can_manage_members', true)
            ->assertJsonPath('data.can_view_finance', true)
            ->assertJsonPath('data.viewer.can_manage_members', true)
            ->assertJsonPath('data.viewer.can_view_finance', true);

        $this->getJson("/api/v1/clubs/{$club->id}/members")
            ->assertOk();
        $this->getJson("/api/v1/clubs/{$club->id}/billing")
            ->assertOk();
        $this->postJson("/api/v1/clubs/{$club->id}/finance-entries", [
            'type' => 'expense',
            'account' => 'cash',
            'title' => 'Nicht erlaubt',
            'amount' => 10,
        ])->assertForbidden();
    }

    public function test_owner_can_grant_finance_permissions_to_one_club_member(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($manager->id, [
            'role' => 'manager',
            'roles' => ['manager'],
            'membership_status' => 'active',
        ]);

        Sanctum::actingAs($owner);
        $this->putJson("/api/v1/clubs/{$club->id}/members/{$manager->id}/permissions", [
            'permissions' => [
                'finance.view' => true,
                'finance.manage' => true,
            ],
        ])
            ->assertOk()
            ->assertJsonFragment(['finance.manage' => true])
            ->assertJsonFragment(['finance.view' => true]);

        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $manager->id,
        ]);

        Sanctum::actingAs($manager);
        $this->getJson("/api/v1/clubs/{$club->id}/billing")
            ->assertOk();
    }

    public function test_member_cannot_change_club_permissions(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]);

        Sanctum::actingAs($member);

        $this->putJson("/api/v1/clubs/{$club->id}/members/{$member->id}/permissions", [
            'permissions' => ['finance.view' => true],
        ])->assertForbidden();
    }

    public function test_legacy_manage_permissions_expand_to_actions_but_explicit_denials_win(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($manager->id, [
            'role' => 'manager', 'roles' => ['manager'], 'membership_status' => 'active',
            'permission_overrides' => [ClubPermissions::MEMBERS_DELETE => false],
        ]);

        $effective = ClubPermissions::effectiveFor($club, $manager);

        $this->assertTrue($effective[ClubPermissions::MEMBERS_EDIT]);
        $this->assertTrue($effective[ClubPermissions::MEMBERS_EXPORT]);
        $this->assertTrue($effective[ClubPermissions::MEMBERS_APPROVE]);
        $this->assertFalse($effective[ClubPermissions::MEMBERS_DELETE]);
    }

    public function test_inactive_members_lose_all_permissions_while_non_member_staff_remain_compatible(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, [
            'role' => 'manager',
            'roles' => ['manager'],
            'membership_status' => 'active',
            'permission_overrides' => [ClubPermissions::FINANCE_APPROVE => true],
        ]);
        $role = ClubRoleDefinition::query()->create([
            'club_id' => $club->id,
            'key' => 'year_period_editor',
            'name' => 'Vereinsjahre bearbeiten',
            'permissions' => [ClubPermissions::YEAR_PERIODS_EDIT],
            'is_active' => true,
        ]);
        ClubRoleAssignment::query()->create([
            'club_id' => $club->id,
            'club_role_definition_id' => $role->id,
            'user_id' => $member->id,
            'scope_type' => 'club',
            'scope_key' => 'club',
            'assigned_by' => $owner->id,
        ]);
        ClubPermissionDelegation::query()->create([
            'club_id' => $club->id,
            'grantor_user_id' => $owner->id,
            'grantee_user_id' => $member->id,
            'permissions' => [ClubPermissions::FINANCE_EXPORT],
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addWeek(),
        ]);

        foreach ([
            ClubPermissions::MEMBERS_EDIT,
            ClubPermissions::FINANCE_APPROVE,
            ClubPermissions::FINANCE_EXPORT,
            ClubPermissions::YEAR_PERIODS_VIEW,
            ClubPermissions::YEAR_PERIODS_EDIT,
        ] as $permission) {
            $this->assertTrue(ClubPermissions::allows($club, $member, $permission), $permission);
        }

        foreach (['pending', 'paused', 'former'] as $membershipStatus) {
            $club->users()->updateExistingPivot($member->id, ['membership_status' => $membershipStatus]);
            foreach (ClubPermissions::ALL as $permission) {
                $this->assertFalse(
                    ClubPermissions::allows($club, $member, $permission),
                    $membershipStatus.':'.$permission,
                );
            }
        }

        $club->users()->updateExistingPivot($member->id, ['membership_status' => 'non_member']);
        foreach ([
            ClubPermissions::MEMBERS_EDIT,
            ClubPermissions::FINANCE_APPROVE,
            ClubPermissions::FINANCE_EXPORT,
            ClubPermissions::YEAR_PERIODS_VIEW,
            ClubPermissions::YEAR_PERIODS_EDIT,
        ] as $permission) {
            $this->assertTrue(ClubPermissions::allows($club, $member, $permission), $permission);
        }
    }

    public function test_delete_only_member_permission_can_remove_a_member_but_cannot_edit_one(): void
    {
        $owner = User::factory()->create();
        $operator = User::factory()->create();
        $victim = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach([
            $operator->id => [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
                'permission_overrides' => [ClubPermissions::MEMBERS_DELETE => true],
            ],
            $victim->id => ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active'],
        ]);
        $role = ClubRoleDefinition::query()->create([
            'club_id' => $club->id, 'key' => 'victim_role', 'name' => 'Victim role',
            'permissions' => [ClubPermissions::FINANCE_VIEW], 'is_active' => true,
        ]);
        ClubRoleAssignment::query()->create([
            'club_id' => $club->id, 'club_role_definition_id' => $role->id,
            'user_id' => $victim->id, 'assigned_by' => $owner->id,
        ]);
        $delegation = ClubPermissionDelegation::query()->create([
            'club_id' => $club->id, 'grantor_user_id' => $owner->id, 'grantee_user_id' => $victim->id,
            'permissions' => [ClubPermissions::FINANCE_VIEW], 'starts_at' => now(), 'ends_at' => now()->addWeek(),
        ]);
        Sanctum::actingAs($operator);

        $this->putJson("/api/v1/clubs/{$club->id}/members/{$victim->id}", [
            'membership_status' => 'active',
        ])->assertForbidden();
        $this->deleteJson("/api/v1/clubs/{$club->id}/members/{$victim->id}", [
            'reason' => 'Auf ausdrücklichen Wunsch beendet',
        ])->assertOk();
        $this->assertDatabaseMissing('club_user', ['club_id' => $club->id, 'user_id' => $victim->id]);
        $this->assertDatabaseMissing('club_role_assignments', ['club_id' => $club->id, 'user_id' => $victim->id]);
        $this->assertNotNull($delegation->fresh()->revoked_at);
        $this->assertDatabaseHas('club_user', ['club_id' => $club->id, 'user_id' => $operator->id]);
    }

    public function test_legacy_profile_role_editor_uses_the_roles_permission_instead_of_generic_club_management(): void
    {
        $owner = User::factory()->create();
        $roleEditor = User::factory()->create();
        $profileEditor = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach([
            $roleEditor->id => [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
                'permission_overrides' => [ClubPermissions::MEMBERS_ROLES => true],
            ],
            $profileEditor->id => [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
                'permission_overrides' => [ClubPermissions::CLUB_PROFILE_EDIT => true],
            ],
            $member->id => ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active'],
        ]);

        $this->actingAs($roleEditor)
            ->putJson(route('auth.clubs.members.update', [$club, $member]), [
                'role' => 'trainer',
                'roles' => ['trainer'],
            ])
            ->assertOk()
            ->assertJsonPath('data.role', 'trainer');

        $this->actingAs($profileEditor)
            ->putJson(route('auth.clubs.members.update', [$club, $member]), [
                'role' => 'member',
                'roles' => ['member'],
            ])
            ->assertForbidden();

        $this->actingAs($roleEditor)
            ->get(route('auth.clubs.show', $club))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('viewer.can_manage', false)
                ->where('viewer.can_manage_roles', true));
    }

    public function test_web_membership_and_finance_writes_use_their_separate_permissions(): void
    {
        $owner = User::factory()->create();
        $memberEditor = User::factory()->create();
        $financeEditor = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach([
            $memberEditor->id => [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
                'permission_overrides' => [ClubPermissions::MEMBERS_EDIT => true],
            ],
            $financeEditor->id => [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
                'permission_overrides' => [ClubPermissions::FINANCE_EDIT => true],
            ],
        ]);

        $this->actingAs($memberEditor)
            ->put(route('auth.club-memberships.settings.update', $club), [
                'membership_requests_enabled' => false,
                'member_pause_requests_enabled' => true,
            ])
            ->assertRedirect();

        $contribution = [
            'name' => 'Jahresbeitrag',
            'valid_from' => '2026-01-01',
            'billing_interval' => 'yearly',
            'amount' => 120,
            'is_active' => true,
        ];

        $this->actingAs($memberEditor)
            ->postJson(route('auth.club-memberships.contribution-rules.store', $club), $contribution)
            ->assertForbidden();

        $this->actingAs($financeEditor)
            ->post(route('auth.club-memberships.contribution-rules.store', $club), $contribution)
            ->assertRedirect();

        $this->actingAs($financeEditor)
            ->putJson(route('auth.club-memberships.settings.update', $club), [
                'membership_requests_enabled' => true,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('club_contribution_rules', [
            'club_id' => $club->id,
            'name' => 'Jahresbeitrag',
        ]);
    }

    public function test_team_editor_can_create_teams_without_generic_club_management(): void
    {
        $owner = User::factory()->create();
        $teamEditor = User::factory()->create();
        $member = User::factory()->create();
        $manager = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach([
            $teamEditor->id => [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
                'permission_overrides' => [ClubPermissions::TEAMS_EDIT => true],
            ],
            $member->id => ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active'],
            $manager->id => [
                'role' => 'manager', 'roles' => ['manager'], 'membership_status' => 'active',
                'permission_overrides' => [ClubPermissions::TEAMS_EDIT => false],
            ],
        ]);

        $token = $teamEditor->createToken('team-editor-test')->plainTextToken;
        $this->getJson("/api/v1/clubs/{$club->id}", ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('data.can_manage', false)
            ->assertJsonPath('data.can_edit_teams', true)
            ->assertJsonPath('data.viewer.can_edit_teams', true);
        $this->postJson('/api/v1/teams', [
            'club_id' => $club->id,
            'name' => 'Nachwuchs',
            'sport_type' => 'Fußball',
        ], ['Authorization' => 'Bearer '.$token])
            ->assertCreated();

        $this->actingAs($teamEditor)
            ->post(route('auth.teams.store'), [
                'club_id' => $club->id,
                'name' => 'Nachwuchs',
                'sport_type' => 'Fußball',
            ])
            ->assertRedirect();

        $memberToken = $member->createToken('member-test')->plainTextToken;
        $this->postJson('/api/v1/teams', [
            'club_id' => $club->id,
            'name' => 'Nicht erlaubt',
        ], ['Authorization' => 'Bearer '.$memberToken])
            ->assertForbidden();

        Sanctum::actingAs($manager);
        $this->getJson("/api/v1/clubs/{$club->id}")
            ->assertOk()
            ->assertJsonPath('data.can_manage', true)
            ->assertJsonPath('data.can_edit_teams', false)
            ->assertJsonPath('data.viewer.can_edit_teams', false);

        $clubScreen = file_get_contents(base_path('mobile/airmius_mobile/lib/screens/clubs_screen.dart'));
        $teamCenter = file_get_contents(base_path('mobile/airmius_mobile/lib/screens/teams_center_screen.dart'));
        $this->assertStringContainsString('if (club.canEditTeams)', $clubScreen);
        $this->assertStringContainsString('panel == \'team\' && club.canEditTeams', $clubScreen);
        $this->assertStringContainsString('.where((club) => club.canEditTeams)', $teamCenter);
        $this->assertStringNotContainsString('club.canManage || club.ownerId == userId', $teamCenter);

        $this->assertDatabaseHas('teams', ['club_id' => $club->id, 'name' => 'Nachwuchs']);
    }
}
