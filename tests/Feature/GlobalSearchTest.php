<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_search_requires_at_least_two_characters(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('auth.search', ['q' => 'a']))
            ->assertOk()
            ->assertExactJson(['results' => []]);
    }

    public function test_global_search_returns_users_clubs_and_teams_for_layout_contract(): void
    {
        $user = User::factory()->create([
            'name' => 'Runner Current',
            'email' => 'current-runner@example.test',
        ]);

        $matchedUser = User::factory()->create([
            'name' => 'Runner Match',
            'email' => 'runner-match@example.test',
        ]);

        $clubOwner = User::factory()->create();
        $club = Club::factory()->create([
            'name' => 'Runner Club',
            'owner_id' => $clubOwner->id,
            'verification_status' => 'verified',
        ]);
        $club->users()->syncWithoutDetaching([
            $user->id => ['role' => 'member', 'roles' => ['member']],
        ]);

        $team = Team::factory()->create([
            'club_id' => $club->id,
            'name' => 'Runner Team',
            'sport_type' => 'strassenlauf',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('auth.search', ['q' => 'Runner']))
            ->assertOk()
            ->assertJsonCount(3, 'results');

        $results = collect($response->json('results'));

        $this->assertTrue($results->contains(fn (array $result) => $result['type'] === 'user' && $result['id'] === $matchedUser->id));
        $this->assertFalse($results->contains(fn (array $result) => $result['type'] === 'user' && $result['id'] === $user->id));
        $this->assertTrue($results->contains(fn (array $result) => $result['type'] === 'club' && $result['id'] === $club->id));
        $this->assertTrue($results->contains(fn (array $result) => $result['type'] === 'team' && $result['id'] === $team->id));
        $this->assertSame(route('auth.teams.join-requests.store', $team->id), $results->firstWhere('type', 'team')['join_url']);
    }
}
