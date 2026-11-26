<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\GuardianChildRelationship;
use App\Models\Team;
use App\Models\User;
use App\Support\TeamRoles;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GuardianMembershipVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_and_web_show_only_clubs_from_active_accepted_guardian_relationships(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        $guardian = User::factory()->create([
            'email' => 'parent@example.test',
            'email_verified_at' => now(),
            'birth_date' => now()->subYears(35)->toDateString(),
        ]);
        $guardian->assignRole('guardian');
        $child = User::factory()->create([
            'name' => 'Kind Airmius',
            'birth_date' => now()->subYears(9)->toDateString(),
            'guardian_user_id' => $guardian->id,
            'guardian_email' => $guardian->email,
            'guardian_consent_at' => now(),
        ]);

        $visibleClub = Club::factory()->create(['owner_id' => User::factory(), 'name' => 'Sichtbarer Verein']);
        $visibleTeam = Team::factory()->create(['club_id' => $visibleClub->id, 'name' => 'U10']);
        $visibleClub->users()->attach($child->id, ['role' => 'player', 'membership_status' => 'active']);
        $visibleTeam->users()->attach($child->id, ['role' => TeamRoles::PLAYER]);
        GuardianChildRelationship::query()->create([
            'club_id' => $visibleClub->id,
            'child_user_id' => $child->id,
            'guardian_user_id' => $guardian->id,
            'guardian_email' => $guardian->email,
            'status' => GuardianChildRelationship::STATUS_ACCEPTED,
            'is_primary' => true,
            'accepted_at' => now(),
        ]);

        $hiddenClub = Club::factory()->create(['owner_id' => User::factory(), 'name' => 'Widerrufener Verein']);
        $hiddenClub->users()->attach($child->id, ['role' => 'player', 'membership_status' => 'active']);
        GuardianChildRelationship::query()->create([
            'club_id' => $hiddenClub->id,
            'child_user_id' => $child->id,
            'guardian_user_id' => $guardian->id,
            'guardian_email' => $guardian->email,
            'status' => GuardianChildRelationship::STATUS_REVOKED,
            'revoked_at' => now(),
        ]);

        Sanctum::actingAs($guardian);
        $this->getJson('/api/v1/guardian/children')
            ->assertOk()
            ->assertJsonCount(1, 'data.children.0.clubs')
            ->assertJsonPath('data.children.0.clubs.0.name', 'Sichtbarer Verein')
            ->assertJsonPath('data.children.0.clubs.0.membership_status', 'active')
            ->assertJsonPath('data.children.0.clubs.0.teams.0.name', 'U10')
            ->assertJsonPath('data.children.0.clubs.0.teams.0.role', TeamRoles::PLAYER)
            ->assertJsonMissing(['name' => 'Widerrufener Verein']);

        $this->actingAs($guardian)
            ->get(route('guardian-access.children'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('children', 1)
                ->where('children.0.clubs.0.name', 'Sichtbarer Verein')
                ->where('children.0.clubs.0.teams.0.name', 'U10')
                ->missing('children.0.clubs.1')
            );
    }

    public function test_unverified_email_match_does_not_expose_club_memberships(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        $guardian = User::factory()->unverified()->create([
            'email' => 'unverified-parent@example.test',
            'birth_date' => now()->subYears(35)->toDateString(),
        ]);
        $guardian->assignRole('guardian');
        $child = User::factory()->create([
            'birth_date' => now()->subYears(9)->toDateString(),
            'guardian_email' => $guardian->email,
            'guardian_consent_at' => now(),
        ]);
        $club = Club::factory()->create(['owner_id' => User::factory(), 'name' => 'Geschützter Verein']);
        $club->users()->attach($child->id, ['role' => 'player', 'membership_status' => 'active']);
        GuardianChildRelationship::query()->create([
            'club_id' => $club->id,
            'child_user_id' => $child->id,
            'guardian_email' => $guardian->email,
            'status' => GuardianChildRelationship::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);

        Sanctum::actingAs($guardian);
        $this->getJson('/api/v1/guardian/children')
            ->assertOk()
            ->assertJsonCount(0, 'data.children.0.clubs')
            ->assertJsonMissing(['name' => 'Geschützter Verein']);
    }
}
