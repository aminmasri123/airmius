<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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
            ->assertOk();
    }
}
