<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Sport;
use App\Models\Team;
use App\Models\SportMatching;
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
            'description' => 'Etwa acht Kilometer in lockerem Tempo.',
            'city' => 'Kenitra',
            'postal_code' => '14000',
            'location_name' => 'Strandpromenade',
            'address' => 'Avenue des Sports 12',
            'country_code' => 'MA',
            'radius_km' => 20,
            'starts_at' => now()->addDay()->toIso8601String(),
            'participants_needed' => 2,
            'skill_level' => 'recreational',
        ])->assertCreated()
            ->assertJsonPath('data.mode', 'partner')
            ->assertJsonPath('data.title', 'Laufen-Sportpartner gesucht')
            ->assertJsonPath('data.postal_code', '14000')
            ->assertJsonPath('data.location_name', 'Strandpromenade')
            ->assertJsonPath('data.address', 'Avenue des Sports 12')
            ->json('data.id');

        Sanctum::actingAs($runner);
        $this->getJson('/api/v1/sport-matching?mode=partner&location=14000&sport_id='.$running->id)
            ->assertOk()
            ->assertJsonPath('data.0.id', $matchingId)
            ->assertJsonPath('data.0.city', 'Kenitra')
            ->assertJsonPath('data.0.address', null)
            ->assertJsonPath('meta.sports.0.slug', 'running');

        $this->postJson("/api/v1/sport-matching/{$matchingId}/apply", [
            'message' => 'Ich laufe gerne mit.',
        ])->assertOk();

        Sanctum::actingAs($owner);
        $applicationId = $this->getJson('/api/v1/sport-matching?mode=partner')
            ->assertOk()
            ->assertJsonPath('data.0.applications.0.user.id', $runner->id)
            ->json('data.0.applications.0.id');

        Sanctum::actingAs($runner);
        $this->putJson("/api/v1/sport-matching/{$matchingId}/applications/{$applicationId}", [
            'status' => 'accepted',
        ])->assertForbidden();
        $this->assertDatabaseCount('conversations', 0);

        Sanctum::actingAs($owner);

        $decisionResponse = $this->putJson("/api/v1/sport-matching/{$matchingId}/applications/{$applicationId}", [
            'status' => 'accepted',
        ])->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $this->assertGreaterThan(0, (int) $decisionResponse->json('conversation_id'));

        $conversationId = $this->getJson('/api/v1/chat/conversations')
            ->assertOk()
            ->json('data.0.id');

        $this->assertDatabaseHas('conversations', [
            'id' => $conversationId,
            'type' => 'direct',
        ]);
        $this->assertDatabaseHas('conversation_users', [
            'conversation_id' => $conversationId,
            'user_id' => $owner->id,
        ]);
        $this->assertDatabaseHas('conversation_users', [
            'conversation_id' => $conversationId,
            'user_id' => $runner->id,
        ]);

        Sanctum::actingAs($owner);
        $this->putJson("/api/v1/sport-matching/{$matchingId}/attendance", [
            'action' => 'confirm',
        ])->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        Sanctum::actingAs($runner);
        $this->putJson("/api/v1/sport-matching/{$matchingId}/attendance", [
            'action' => 'confirm',
        ])->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        SportMatching::query()->whereKey($matchingId)->update([
            'starts_at' => now()->addHour(),
        ]);

        $this->artisan('airmius:send-sport-matching-reminders')
            ->assertExitCode(0);

        $this->assertDatabaseHas('sport_matching_attendances', [
            'sport_matching_id' => $matchingId,
        ]);
        $this->assertNotNull(
            \App\Models\SportMatchingAttendance::query()
                ->where('sport_matching_id', $matchingId)
                ->value('reminder_2h_sent_at')
        );

        SportMatching::query()->whereKey($matchingId)->update([
            'starts_at' => now()->subHour(),
        ]);

        $this->postJson("/api/v1/sport-matching/{$matchingId}/attendance/no-show", [
            'target_user_id' => $owner->id,
            'reason' => 'Keine Ankunft und keine Absage.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'no_show');

        $this->assertDatabaseHas('sport_matching_attendances', [
            'sport_matching_id' => $matchingId,
            'user_id' => $owner->id,
            'status' => 'no_show',
            'no_show_reported_by' => $runner->id,
        ]);
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

    public function test_swipe_dismissal_is_persistent_and_can_be_restored(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $sport = Sport::query()->create([
            'name' => 'Tennis',
            'slug' => 'tennis',
            'category' => 'racket',
            'is_active' => true,
        ]);

        Sanctum::actingAs($owner);
        $matchingId = $this->postJson('/api/v1/sport-matching', [
            'mode' => 'partner',
            'sport_id' => $sport->id,
            'city' => 'Berlin',
            'country_code' => 'DE',
            'radius_km' => 25,
            'starts_at' => now()->addDay()->toIso8601String(),
            'participants_needed' => 1,
            'skill_level' => 'all',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($viewer);
        $this->postJson("/api/v1/sport-matching/{$matchingId}/dismiss")
            ->assertOk()
            ->assertJsonPath('data.dismissed', true);

        $this->getJson('/api/v1/sport-matching')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->postJson("/api/v1/sport-matching/{$matchingId}/dismiss", ['dismissed' => false])
            ->assertOk()
            ->assertJsonPath('data.dismissed', false);

        $this->getJson('/api/v1/sport-matching')
            ->assertOk()
            ->assertJsonPath('data.0.id', $matchingId);
    }

    public function test_distance_filter_uses_coordinates_instead_of_offer_radius(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $sport = Sport::query()->create(['name' => 'Laufen', 'slug' => 'running', 'category' => 'endurance', 'is_active' => true]);

        Sanctum::actingAs($owner);
        foreach ([['Berlin', 52.5200, 13.4050], ['Hamburg', 53.5503, 10.0007]] as [$city, $lat, $lng]) {
            $this->postJson('/api/v1/sport-matching', [
                'mode' => 'partner', 'sport_id' => $sport->id, 'city' => $city,
                'country_code' => 'DE', 'latitude' => $lat, 'longitude' => $lng,
                'radius_km' => 500, 'starts_at' => now()->addDay()->toIso8601String(),
                'participants_needed' => 8, 'skill_level' => 'all',
            ])->assertCreated()->assertJsonPath('data.participants_needed', 1);
        }

        Sanctum::actingAs($viewer);
        $this->getJson('/api/v1/sport-matching?latitude=52.5200&longitude=13.4050&radius_km=25')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.city', 'Berlin')
            ->assertJsonPath('data.0.latitude', null)->assertJsonPath('data.0.distance_km', 0);
    }

    public function test_minimum_team_size_and_full_offer_are_enforced(): void
    {
        $owner = User::factory()->create();
        $opponent = User::factory()->create();
        $second = User::factory()->create();
        $sport = Sport::query()->create(['name' => 'Fußball', 'slug' => 'football', 'category' => 'team', 'is_active' => true]);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $team->users()->attach($owner->id, ['role' => 'Coach']);
        $otherClub = Club::factory()->create(['owner_id' => $opponent->id]);
        $otherTeam = Team::factory()->create(['club_id' => $otherClub->id]);
        $otherTeam->users()->attach($opponent->id, ['role' => 'Coach']);
        $otherTeam->users()->attach($second->id, ['role' => 'Player']);

        Sanctum::actingAs($owner);
        $id = $this->postJson('/api/v1/sport-matching', [
            'mode' => 'team', 'sport_id' => $sport->id, 'team_id' => $team->id,
            'city' => 'Berlin', 'country_code' => 'DE', 'radius_km' => 25,
            'starts_at' => now()->addDay()->toIso8601String(), 'participants_needed' => 1,
            'own_team_size' => 8, 'team_size' => 5, 'opponent_size_type' => 'minimum',
            'skill_level' => 'all',
        ])->assertCreated()->assertJsonPath('data.own_team_size', 8)
            ->assertJsonPath('data.opponent_size_type', 'minimum')->json('data.id');

        Sanctum::actingAs($opponent);
        $this->postJson("/api/v1/sport-matching/{$id}/apply", ['team_id' => $otherTeam->id, 'team_size' => 4])->assertUnprocessable();
        $this->postJson("/api/v1/sport-matching/{$id}/apply", ['team_id' => $otherTeam->id, 'team_size' => 7])->assertOk();
        $this->postJson("/api/v1/sport-matching/{$id}/withdraw")->assertOk();
        $this->postJson("/api/v1/sport-matching/{$id}/apply", ['team_id' => $otherTeam->id, 'team_size' => 7])->assertOk();

        Sanctum::actingAs($owner);
        $applicationId = $this->getJson('/api/v1/sport-matching?mode=team')->json('data.0.applications.0.id');
        $this->putJson("/api/v1/sport-matching/{$id}/applications/{$applicationId}", ['status' => 'accepted'])
            ->assertOk();
        $this->assertDatabaseHas('sport_matchings', ['id' => $id, 'status' => 'matched']);

        Sanctum::actingAs($second);
        $this->postJson("/api/v1/sport-matching/{$id}/apply", ['team_id' => $otherTeam->id, 'team_size' => 7])
            ->assertUnprocessable();
    }
}
