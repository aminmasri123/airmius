<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Sport;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SportMatchingFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_web_workspace_renders_both_matching_modes(): void
    {
        $user = User::factory()->create();
        Sport::query()->create([
            'name' => 'Padel',
            'slug' => 'padel',
            'category' => 'racket',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/sport-matching')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/SportMatching/Index')
                ->has('sports', 1)
                ->has('matchings.data', 0));
    }

    public function test_people_can_find_sport_partners_across_cities_and_sports(): void
    {
        $owner = User::factory()->create();
        $runner = User::factory()->create();
        $running = Sport::query()->create([
            'name' => 'Laufen',
            'slug' => 'running',
            'category' => 'endurance',
            'is_active' => true,
        ]);

        Sanctum::actingAs($owner);
        $matchingId = $this->postJson('/api/v1/sport-matching', [
            'mode' => 'partner',
            'sport_id' => $running->id,
            'title' => 'Lauf am Strand von Kenitra',
            'description' => 'Etwa acht Kilometer in lockerem Tempo.',
            'city' => 'Kenitra',
            'country_code' => 'MA',
            'radius_km' => 20,
            'starts_at' => now()->addDay()->toIso8601String(),
            'participants_needed' => 2,
            'skill_level' => 'recreational',
        ])->assertCreated()->assertJsonPath('data.mode', 'partner')->json('data.id');

        Sanctum::actingAs($runner);
        $this->getJson('/api/v1/sport-matching?mode=partner&city=Kenitra&sport_id='.$running->id)
            ->assertOk()
            ->assertJsonPath('data.0.id', $matchingId)
            ->assertJsonPath('data.0.city', 'Kenitra')
            ->assertJsonPath('meta.sports.0.slug', 'running');

        $this->postJson("/api/v1/sport-matching/{$matchingId}/apply", [
            'message' => 'Ich laufe gerne mit.',
        ])->assertOk();

        Sanctum::actingAs($owner);
        $applicationId = $this->getJson('/api/v1/sport-matching?mode=partner')
            ->assertOk()
            ->assertJsonPath('data.0.applications.0.user.id', $runner->id)
            ->json('data.0.applications.0.id');

        $this->putJson("/api/v1/sport-matching/{$matchingId}/applications/{$applicationId}", [
            'status' => 'accepted',
        ])->assertOk()->assertJsonPath('data.status', 'accepted');
    }

    public function test_any_sport_can_match_team_against_team_with_a_custom_team_size(): void
    {
        $challenger = User::factory()->create();
        $opponent = User::factory()->create();
        $sport = Sport::query()->create([
            'name' => 'Basketball',
            'slug' => 'basketball',
            'category' => 'team',
            'is_active' => true,
        ]);
        $clubA = Club::factory()->create(['owner_id' => $challenger->id]);
        $clubB = Club::factory()->create(['owner_id' => $opponent->id]);
        $teamA = Team::factory()->create(['club_id' => $clubA->id, 'sport_type' => 'basketball']);
        $teamB = Team::factory()->create(['club_id' => $clubB->id, 'sport_type' => 'basketball']);
        $teamA->users()->attach($challenger->id, ['role' => 'Coach']);
        $teamB->users()->attach($opponent->id, ['role' => 'Coach']);

        Sanctum::actingAs($challenger);
        $matchingId = $this->postJson('/api/v1/sport-matching', [
            'mode' => 'team',
            'sport_id' => $sport->id,
            'team_id' => $teamA->id,
            'title' => 'Freundschaftsspiel gesucht',
            'city' => 'Berlin',
            'country_code' => 'DE',
            'radius_km' => 50,
            'starts_at' => now()->addDays(2)->toIso8601String(),
            'participants_needed' => 99,
            'team_size' => 5,
            'skill_level' => 'competitive',
        ])->assertCreated()
            ->assertJsonPath('data.team_size', 5)
            ->assertJsonPath('data.participants_needed', 1)
            ->json('data.id');

        Sanctum::actingAs($opponent);
        $this->postJson("/api/v1/sport-matching/{$matchingId}/apply", [
            'team_id' => $teamB->id,
            'message' => 'Wir sind dabei.',
        ])->assertOk();

        Sanctum::actingAs($challenger);
        $applicationId = $this->getJson('/api/v1/sport-matching?mode=team')
            ->json('data.0.applications.0.id');
        $this->putJson("/api/v1/sport-matching/{$matchingId}/applications/{$applicationId}", [
            'status' => 'accepted',
        ])->assertOk();

        $this->assertDatabaseHas('sport_matchings', [
            'id' => $matchingId,
            'mode' => 'team',
            'team_size' => 5,
            'status' => 'matched',
        ]);
    }
}
