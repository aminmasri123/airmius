<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubRoleAssignment;
use App\Models\ClubRoleDefinition;
use App\Models\Team;
use App\Models\User;
use App\Support\ClubPermissions;
use App\Support\NavigationModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubMembershipAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_club_member_cannot_open_membership_management(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);

        $club->users()->syncWithoutDetaching([
            $user->id => [
                'role' => 'member',
                'roles' => ['member'],
                'membership_status' => 'active',
            ],
        ]);

        $this->actingAs($user)
            ->get(route('auth.club-memberships.index'))
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Errors/Forbidden')
                ->where('title', 'Du hast dafür keine Berechtigung')
                ->where('message', 'Du hast dafür keine Berechtigung. Bitte wende dich an deinen Verein/Admin oder prüfe dein Paket.')
            );
    }

    public function test_team_coach_without_club_management_role_cannot_open_membership_management(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);

        $club->users()->syncWithoutDetaching([
            $user->id => [
                'role' => 'member',
                'roles' => ['member'],
                'membership_status' => 'active',
            ],
        ]);

        $team->users()->syncWithoutDetaching([
            $user->id => ['role' => 'Coach'],
        ]);

        $this->actingAs($user)
            ->get(route('auth.club-memberships.index'))
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Errors/Forbidden')
                ->where('title', 'Du hast dafür keine Berechtigung')
                ->where('message', 'Du hast dafür keine Berechtigung. Bitte wende dich an deinen Verein/Admin oder prüfe dein Paket.')
            );
    }

    public function test_club_manager_can_open_membership_management(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);

        $club->users()->syncWithoutDetaching([
            $user->id => [
                'role' => 'manager',
                'roles' => ['manager'],
                'membership_status' => 'active',
            ],
        ]);

        $this->actingAs($user)
            ->get(route('auth.club-memberships.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/ClubMemberships/Index')
                ->where('clubs.0.can_manage_access', false));
    }

    public function test_owner_receives_the_access_management_contract(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
        ]);
        Team::factory()->create(['club_id' => $club->id, 'name' => 'Zugriffsteam']);

        $this->actingAs($owner)
            ->get(route('auth.club-memberships.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/ClubMemberships/Index')
                ->where('clubs.0.owner_id', $owner->id)
                ->where('clubs.0.can_manage_access', true)
                ->where('clubs.0.teams.0.name', 'Zugriffsteam')
                ->has('clubs.0.members', 2));
    }

    public function test_configured_role_manager_can_open_the_access_workspace(): void
    {
        $owner = User::factory()->create();
        $roleManager = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($roleManager->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
        ]);
        $role = ClubRoleDefinition::query()->create([
            'club_id' => $club->id,
            'key' => 'access_manager',
            'name' => 'Zugriffsverwaltung',
            'permissions' => [ClubPermissions::MEMBERS_ROLES],
            'is_active' => true,
        ]);
        ClubRoleAssignment::query()->create([
            'club_id' => $club->id,
            'club_role_definition_id' => $role->id,
            'user_id' => $roleManager->id,
            'scope_type' => 'club',
            'scope_id' => null,
            'scope_key' => 'club',
            'assigned_by' => $owner->id,
        ]);

        $this->actingAs($roleManager)
            ->get(route('auth.club-memberships.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/ClubMemberships/Index')
                ->where('clubs.0.id', $club->id)
                ->where('clubs.0.can_manage_access', true));
    }

    public function test_specialist_permissions_publish_membership_navigation_without_an_elevated_club_role(): void
    {
        foreach ([
            ClubPermissions::MEMBERS_MANAGE,
            ClubPermissions::FINANCE_VIEW,
            ClubPermissions::MEMBERS_ROLES,
        ] as $permission) {
            $owner = User::factory()->create();
            $specialist = User::factory()->create();
            $club = Club::factory()->create(['owner_id' => $owner->id]);
            $club->users()->attach($specialist->id, [
                'role' => 'member',
                'roles' => ['member'],
                'membership_status' => 'active',
                'permission_overrides' => [$permission => true],
            ]);

            $this->assertContains(NavigationModules::CLUB, NavigationModules::availableFor($specialist));

            $this->actingAs($specialist)
                ->get(route('auth.club-memberships.index'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('auth.user.can', fn ($permissions) => $permissions['club-memberships.view'] === true)
                    ->where('clubs.0.id', $club->id));
        }
    }

    public function test_explicit_manager_denials_remove_membership_navigation_and_access(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($manager->id, [
            'role' => 'manager',
            'roles' => ['manager'],
            'membership_status' => 'active',
            'permission_overrides' => [
                ClubPermissions::MEMBERS_MANAGE => false,
                ClubPermissions::FINANCE_VIEW => false,
                ClubPermissions::MEMBERS_ROLES => false,
            ],
        ]);

        $this->assertNotContains(NavigationModules::CLUB, NavigationModules::availableFor($manager));

        $this->actingAs($manager)
            ->get(route('auth.club-memberships.index'))
            ->assertForbidden();

        Sanctum::actingAs($manager);
        $this->getJson("/api/v1/clubs/{$club->id}")
            ->assertOk()
            ->assertJsonPath('data.can_manage', true)
            ->assertJsonPath('data.can_manage_members', false)
            ->assertJsonPath('data.can_view_finance', false);

        $membershipScreen = file_get_contents(base_path('mobile/airmius_mobile/lib/screens/club_membership_management_screen.dart'));
        $clubsScreen = file_get_contents(base_path('mobile/airmius_mobile/lib/screens/clubs_screen.dart'));
        $this->assertStringContainsString('.where((club) => club.canAccessMembershipWorkspace)', $membershipScreen);
        $this->assertStringContainsString('(club) => club.canAccessMembershipWorkspace', $clubsScreen);
        $this->assertStringNotContainsString('.where((club) => club.canManage)', $membershipScreen);
    }

    public function test_access_manager_connects_all_governance_apis_and_locales(): void
    {
        $page = file_get_contents(resource_path('js/Pages/Auth/Dashboard/ClubMemberships/Index.vue'));
        $component = file_get_contents(resource_path('js/Components/ClubMemberships/ClubAccessManager.vue'));

        $this->assertStringContainsString('ClubAccessManager', $page);
        $this->assertStringContainsString('selectedClub.can_manage_access', $page);
        foreach ([
            'clubs.members.permissions.update',
            'clubs.members.role-definitions.update',
            'clubs.role-definitions.store',
            'clubs.role-definitions.update',
            'clubs.role-definitions.destroy',
            'clubs.permission-delegations.store',
            'clubs.permission-delegations.revoke',
            'clubs.access-handover-reviews.index',
            'clubs.access-handover-reviews.propose',
            'clubs.access-handover-reviews.approve',
        ] as $routeName) {
            $this->assertStringContainsString($routeName, $component);
        }
        foreach (['de: {', 'en: {', 'fr: {', 'ar: {'] as $locale) {
            $this->assertStringContainsString($locale, $component);
        }
        $this->assertStringContainsString('role="tablist"', $component);
        $this->assertStringContainsString(':aria-selected="tab === item"', $component);
        $this->assertStringContainsString('role="alert"', $component);
        $this->assertStringNotContainsString('v-html', $component);
    }
}
