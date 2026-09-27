<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeamIndexApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_club_team_index_only_returns_teams_from_requested_managed_club(): void
    {
        $manager = User::factory()->create();
        $managedClub = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $otherClub = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $managedClub->users()->attach($manager->id, [
            'role' => 'manager',
            'roles' => ['manager'],
            'membership_status' => 'active',
        ]);
        $managedTeam = Team::factory()->create(['club_id' => $managedClub->id]);
        Team::factory()->create(['club_id' => $otherClub->id]);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/teams?club_id='.$managedClub->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $managedTeam->id);
    }

    public function test_club_team_index_rejects_an_unmanaged_club_scope(): void
    {
        $manager = User::factory()->create();
        $managedClub = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $otherClub = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $managedClub->users()->attach($manager->id, [
            'role' => 'manager',
            'roles' => ['manager'],
            'membership_status' => 'active',
        ]);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/teams?club_id='.$otherClub->id)
            ->assertForbidden();
    }
}
