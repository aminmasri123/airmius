<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\SportRoute;
use App\Models\SportRouteTrack;
use App\Models\Team;
use App\Models\TrainingLog;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrainingRouteWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_training_log_form_formats_saved_route_distances_without_crashing(): void
    {
        $source = file_get_contents(base_path('resources/js/Pages/Auth/Dashboard/Training/LogCreate.vue'));

        $this->assertStringContainsString('const formatDistance = (meters) =>', $source);
        $this->assertStringContainsString('formatDistance(sportRoute.distance_meters)', $source);
        $this->assertStringContainsString('formatDistance(sportTrack.distance_meters)', $source);
        $this->assertLessThan(
            strpos($source, 'formatDistance(sportRoute.distance_meters)'),
            strpos($source, 'const formatDistance = (meters) =>'),
            'The formatter must be initialized before Vue renders saved route options.'
        );
    }

    public function test_flutter_source_uses_the_minimized_training_route_contract(): void
    {
        $client = file_get_contents(base_path('mobile/airmius_mobile/lib/core/airmius_api_client.dart'));
        $trainingScreen = file_get_contents(base_path('mobile/airmius_mobile/lib/screens/training_plans_logs_screen.dart'));
        $mapScreen = file_get_contents(base_path('mobile/airmius_mobile/lib/screens/sport_map_center_screen.dart'));
        $translations = file_get_contents(base_path('mobile/airmius_mobile/lib/core/airmius_l10n.dart'));

        $this->assertStringContainsString("'/api/v1/training/route-options'", $client);
        $this->assertStringContainsString("'sport_route_id': _sportRouteId", $trainingScreen);
        $this->assertStringContainsString("'sport_route_track_id': _sportRouteTrackId", $trainingScreen);
        $this->assertStringContainsString('SportMapCenterScreen(', $trainingScreen);
        $this->assertStringContainsString('this.initialRouteId', $mapScreen);
        $this->assertSame(4, substr_count($translations, "'trainingHub.locationMinimized':"));
    }

    public function test_private_route_is_explicitly_shared_through_plan_without_location_data_in_training_payloads(): void
    {
        [$coach, $athlete, , $plan] = $this->fixture();
        $outsider = User::factory()->create();
        $route = $this->route($coach, 'Private Wettkampfstrecke');

        Sanctum::actingAs($coach);

        $created = $this->postJson("/api/v1/training/plans/{$plan->id}/items", [
            'title' => 'Streckenprobe',
            'sport_type' => 'laufen',
            'sport_route_id' => $route->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.items.0.sport_route.id', $route->id)
            ->assertJsonPath('data.items.0.sport_route.title', 'Private Wettkampfstrecke')
            ->assertJsonMissingPath('data.items.0.sport_route.waypoints')
            ->assertJsonMissingPath('data.items.0.sport_route.route_geometry')
            ->assertJsonMissingPath('data.items.0.sport_route.start');

        $itemId = $created->json('data.items.0.id');
        $this->assertDatabaseHas('training_plan_items', [
            'id' => $itemId,
            'sport_route_id' => $route->id,
        ]);

        Sanctum::actingAs($athlete);

        $this->getJson("/api/v1/training/plans/{$plan->id}")
            ->assertOk()
            ->assertJsonPath('data.items.0.sport_route.id', $route->id)
            ->assertJsonMissingPath('data.items.0.sport_route.route_geometry');

        // Opening the map is a deliberate second request; only there is the
        // exact geometry returned to an assigned athlete.
        $this->getJson("/api/v1/sport-routes/{$route->id}")
            ->assertOk()
            ->assertJsonPath('data.route_geometry.type', 'LineString');

        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/sport-routes/{$route->id}")->assertNotFound();
    }

    public function test_route_options_and_log_links_are_owner_scoped_and_coordinate_free(): void
    {
        [, $athlete, $team, $plan] = $this->fixture();
        $teammate = User::factory()->create();
        $team->users()->attach($teammate->id, ['role' => TeamRoles::PLAYER]);

        $route = $this->route($athlete, 'Eigene Runde');
        $otherRoute = $this->route($athlete, 'Andere Runde');
        $track = $this->track($athlete, $route, 'Eigene GPS-Runde');
        $foreignTrack = $this->track($teammate, $route, 'Fremde Team-Aufzeichnung', $team);
        $item = $plan->items()->create([
            'title' => 'Routenlauf',
            'sport_route_id' => $route->id,
            'sort_order' => 1,
        ]);

        Sanctum::actingAs($athlete);

        $this->getJson('/api/v1/training/route-options')
            ->assertOk()
            ->assertJsonPath('data.location_payload', 'summary_only')
            ->assertJsonFragment(['id' => $track->id, 'title' => 'Eigene GPS-Runde'])
            ->assertJsonMissing(['title' => 'Fremde Team-Aufzeichnung'])
            ->assertJsonMissingPath('data.routes.0.route_geometry')
            ->assertJsonMissingPath('data.tracks.0.track_points');

        $created = $this->postJson('/api/v1/training/logs', [
            'training_plan_item_id' => $item->id,
            'sport_route_track_id' => $track->id,
            'title' => 'Routenlauf erledigt',
            'status' => 'completed',
            'privacy_scope' => 'private',
        ])
            ->assertCreated()
            ->assertJsonPath('data.sport_route.id', $route->id)
            ->assertJsonPath('data.sport_route_track.id', $track->id)
            ->assertJsonMissingPath('data.sport_route.route_geometry')
            ->assertJsonMissingPath('data.sport_route_track.track_points');

        $this->assertDatabaseHas('training_logs', [
            'id' => $created->json('data.id'),
            'sport_route_id' => $route->id,
            'sport_route_track_id' => $track->id,
        ]);

        $otherTrack = $this->track($athlete, $otherRoute, 'Nicht passende Spur');
        $this->postJson('/api/v1/training/logs', [
            'sport_route_id' => $route->id,
            'sport_route_track_id' => $otherTrack->id,
            'title' => 'Ungültige Kombination',
            'status' => 'completed',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sport_route_track_id');
    }

    public function test_private_log_does_not_expand_route_visibility_and_plan_resource_has_a_constant_query_budget(): void
    {
        [$coach, $athlete, , $plan] = $this->fixture();
        $privateRoute = $this->route($athlete, 'Nur meine Wohnrunde');
        $log = TrainingLog::query()->create([
            'user_id' => $athlete->id,
            'created_by' => $athlete->id,
            'sport_route_id' => $privateRoute->id,
            'title' => 'Trainer-sichtbarer Log',
            'status' => 'completed',
            'metrics' => ['privacy_scope' => 'trainer'],
        ]);

        Sanctum::actingAs($coach);

        $this->getJson("/api/v1/training/logs/{$log->id}")
            ->assertOk()
            ->assertJsonPath('data.sport_route', null)
            ->assertJsonPath('data.sport_route_id', null);

        $sharedRoute = $this->route($coach, 'Skalierbare Planroute');
        foreach (range(1, 25) as $index) {
            $plan->items()->create([
                'title' => "Einheit {$index}",
                'sport_route_id' => $sharedRoute->id,
                'sort_order' => $index,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson("/api/v1/training/plans/{$plan->id}")
            ->assertOk()
            ->assertJsonCount(25, 'data.items')
            ->assertJsonPath('data.items.24.sport_route.id', $sharedRoute->id);

        $routeQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query) => str_contains(strtolower($query['query']), 'sport_routes'));

        $this->assertLessThanOrEqual(1, $routeQueries->count(), 'All plan route references must be loaded in one route query.');
        $this->assertLessThanOrEqual(50, count(DB::getQueryLog()), 'The complete authenticated request must keep its fixed query budget.');
        DB::disableQueryLog();
    }

    /** @return array{User, User, Team, TrainingPlan} */
    private function fixture(): array
    {
        $coach = User::factory()->create();
        $athlete = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $coach->id]);
        $team = Team::factory()->create(['club_id' => $club->id, 'sport_type' => 'laufen']);
        $team->users()->attach($coach->id, ['role' => TeamRoles::COACH]);
        $team->users()->attach($athlete->id, ['role' => TeamRoles::PLAYER]);

        $plan = TrainingPlan::query()->create([
            'created_by' => $coach->id,
            'team_id' => $team->id,
            'title' => 'Routenplan',
            'cadence' => 'weekly',
            'status' => 'published',
            'share_permission' => 'read',
        ]);
        $plan->assignments()->create([
            'user_id' => $athlete->id,
            'permission' => 'read',
        ]);

        return [$coach, $athlete, $team, $plan];
    }

    private function route(User $owner, string $title): SportRoute
    {
        return SportRoute::query()->create([
            'user_id' => $owner->id,
            'title' => $title,
            'sport_type' => 'running',
            'visibility' => 'private',
            'status' => 'planned',
            'distance_meters' => 7250,
            'estimated_duration_seconds' => 2700,
            'elevation_gain_meters' => 110,
            'waypoints' => [
                ['latitude' => 52.520008, 'longitude' => 13.404954],
                ['latitude' => 52.530008, 'longitude' => 13.414954],
            ],
            'route_geometry' => [
                'type' => 'LineString',
                'coordinates' => [[13.404954, 52.520008], [13.414954, 52.530008]],
            ],
        ]);
    }

    private function track(User $owner, SportRoute $route, string $title, ?Team $team = null): SportRouteTrack
    {
        return SportRouteTrack::query()->create([
            'user_id' => $owner->id,
            'sport_route_id' => $route->id,
            'team_id' => $team?->id,
            'title' => $title,
            'sport_type' => 'running',
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'ended_at' => now(),
            'distance_meters' => 7100,
            'duration_seconds' => 2650,
            'track_points' => [
                ['latitude' => 52.520008, 'longitude' => 13.404954],
                ['latitude' => 52.530008, 'longitude' => 13.414954],
            ],
        ]);
    }
}
