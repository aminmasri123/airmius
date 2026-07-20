<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Team;
use App\Models\User;
use App\Support\ClubRoles;
use App\Support\TeamRoles;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PersonaPermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_sportler_can_open_core_pages_but_not_club_management(): void
    {
        $sportler = User::factory()->create();
        $workspace = $this->clubWorkspace($sportler, ClubRoles::primary(['member']), TeamRoles::PLAYER);

        $this->actingAs($sportler)
            ->withSession(['club_id' => $workspace['club']->id])
            ->get(route('auth.feed.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Dashboard/Feed/Index'));

        $this->actingAs($sportler)
            ->withSession(['club_id' => $workspace['club']->id])
            ->get(route('auth.teams.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/Teams/Index')
                ->where('clubs.0.teams.0.viewer_is_member', true));

        $this->actingAs($sportler)
            ->get(route('auth.club-memberships.index'))
            ->assertForbidden();
    }

    public function test_trainer_can_open_trainer_cockpit_but_not_club_management(): void
    {
        $trainer = User::factory()->create();
        $this->clubWorkspace($trainer, 'trainer', TeamRoles::COACH);

        $this->actingAs($trainer)
            ->get(route('auth.trainer-cockpit.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/TrainerCockpit/Index')
                ->where('summary.teams', 1));

        $this->actingAs($trainer)
            ->get(route('auth.club-memberships.index'))
            ->assertForbidden();
    }

    public function test_verein_admin_can_open_club_membership_management(): void
    {
        $admin = User::factory()->create();
        $workspace = $this->clubWorkspace($admin, 'admin', TeamRoles::CLUB_PRESIDENT);

        $this->actingAs($admin)
            ->withSession(['club_id' => $workspace['club']->id])
            ->get(route('auth.club-memberships.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/ClubMemberships/Index')
                ->where('clubs.0.name', 'Persona Test Club'));
    }

    public function test_elternteil_can_open_guardian_children_but_not_club_management(): void
    {
        $this->seed(RolesPermissionsSeeder::class);

        $guardian = User::factory()->create([
            'email' => 'guardian-persona@example.test',
            'birth_date' => now()->subYears(36)->toDateString(),
        ]);
        $guardian->assignRole('guardian');

        $minor = User::factory()->create([
            'birth_date' => now()->subYears(12)->toDateString(),
            'guardian_email' => 'guardian-persona@example.test',
            'guardian_user_id' => $guardian->id,
            'guardian_consent_at' => now(),
        ]);

        $this->actingAs($guardian)
            ->get(route('guardian-access.children'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Guardian/Children')
                ->where('hasAuthenticatedAccount', true)
                ->where('children.0.id', $minor->id));

        $this->actingAs($guardian)
            ->get(route('auth.club-memberships.index'))
            ->assertForbidden();
    }

    private function clubWorkspace(User $user, string $clubRole, string $teamRole): array
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create([
            'owner_id' => $owner->id,
            'name' => 'Persona Test Club',
            'is_listed' => true,
            'teams_are_listed' => true,
            'members_can_post_to_club' => true,
            'members_can_post_to_teams' => true,
            'verification_status' => 'verified',
        ]);
        $team = Team::factory()->create([
            'club_id' => $club->id,
            'name' => 'Persona Test Team',
        ]);

        $club->users()->syncWithoutDetaching([
            $user->id => [
                'role' => $clubRole,
                'roles' => [$clubRole],
                'membership_status' => 'active',
            ],
        ]);
        $team->users()->syncWithoutDetaching([
            $user->id => ['role' => $teamRole],
        ]);

        return [
            'club' => $club,
            'team' => $team,
        ];
    }
}
