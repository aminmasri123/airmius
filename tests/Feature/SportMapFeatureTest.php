<?php

namespace Tests\Feature;

use App\Models\SportPlace;
use App\Models\SportRoute;
use App\Models\SportRouteTrack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SportMapFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_user_can_plan_route_track_distance_and_add_sport_place(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('auth.sport-map.index'))
            ->assertOk();

        $this->actingAs($user)
            ->post(route('auth.sport-routes.store'), [
                'title' => 'Abendlauf am Park',
                'sport_type' => 'running',
                'visibility' => 'public',
                'difficulty' => 'easy',
                'waypoints' => [
                    ['name' => 'Start', 'latitude' => 48.137154, 'longitude' => 11.576124],
                    ['name' => 'Ziel', 'latitude' => 48.142154, 'longitude' => 11.586124],
                ],
            ])
            ->assertRedirect();

        $route = SportRoute::query()->firstOrFail();

        $this->assertSame('Abendlauf am Park', $route->title);
        $this->assertGreaterThan(0, $route->distance_meters);
        $this->assertCount(2, $route->navigation_cues);

        $this->actingAs($user)
            ->post(route('auth.sport-tracks.store'), [
                'title' => 'Getrackter Abendlauf',
                'sport_route_id' => $route->id,
                'sport_type' => 'running',
                'status' => 'completed',
                'started_at' => now()->subMinutes(20)->toIso8601String(),
                'ended_at' => now()->toIso8601String(),
                'track_points' => [
                    ['latitude' => 48.137154, 'longitude' => 11.576124, 'recorded_at' => now()->subMinutes(20)->toIso8601String()],
                    ['latitude' => 48.142154, 'longitude' => 11.586124, 'recorded_at' => now()->toIso8601String()],
                ],
            ])
            ->assertRedirect();

        $track = SportRouteTrack::query()->firstOrFail();

        $this->assertSame($route->id, $track->sport_route_id);
        $this->assertSame('completed', $track->status);
        $this->assertGreaterThan(0, $track->distance_meters);

        $this->actingAs($user)
            ->post(route('auth.sport-places.store'), [
                'name' => 'Sportpark Mitte',
                'type' => 'football_pitch',
                'latitude' => 48.14,
                'longitude' => 11.58,
                'city' => 'Muenchen',
                'country_code' => 'DE',
                'visibility' => 'public',
                'sport_types' => ['football', 'running'],
                'amenities' => ['floodlights', 'locker_room'],
                'surfaces' => ['grass'],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('sport_places', [
            'name' => 'Sportpark Mitte',
            'type' => 'football_pitch',
            'status' => 'active',
        ]);
    }

    public function test_mobile_sport_map_contract_supports_routes_tracks_and_nearby_places(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->withHeader('X-Locale', 'en')->postJson('/api/v1/sport-routes', [
            'title' => 'Team route without team',
            'sport_type' => 'running',
            'visibility' => 'team',
            'waypoints' => [
                ['name' => 'Start', 'latitude' => 52.52, 'longitude' => 13.405],
                ['name' => 'Finish', 'latitude' => 52.526, 'longitude' => 13.42],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Please select a team for team visibility.');

        $this->postJson('/api/v1/sport-routes', [
            'title' => 'Unknown sport route',
            'sport_type' => 'hoverboard',
            'visibility' => 'public',
            'waypoints' => [
                ['name' => 'Start', 'latitude' => 52.52, 'longitude' => 13.405],
                ['name' => 'Finish', 'latitude' => 52.526, 'longitude' => 13.42],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sport_type');

        $routeResponse = $this->postJson('/api/v1/sport-routes', [
            'title' => 'Mobile Laufroute',
            'sport_type' => 'running',
            'visibility' => 'public',
            'waypoints' => [
                ['name' => 'Start', 'latitude' => 52.52, 'longitude' => 13.405],
                ['name' => 'Checkpoint', 'latitude' => 52.522, 'longitude' => 13.415],
                ['name' => 'Ziel', 'latitude' => 52.526, 'longitude' => 13.42],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Mobile Laufroute')
            ->assertJsonPath('data.navigation_cues.0.type', 'start');

        $routeId = $routeResponse->json('data.id');
        $this->assertGreaterThan(0, $routeResponse->json('data.distance_meters'));

        $this->withHeader('X-Locale', 'en')->postJson("/api/v1/sport-routes/{$routeId}/duplicate")
            ->assertCreated()
            ->assertJsonPath('data.title', 'Mobile Laufroute Copy')
            ->assertJsonPath('data.visibility', 'private');

        $trackResponse = $this->postJson('/api/v1/sport-tracks', [
            'title' => 'Live Track',
            'sport_route_id' => $routeId,
            'sport_type' => 'running',
            'status' => 'recording',
            'track_points' => [
                ['latitude' => 52.52, 'longitude' => 13.405, 'recorded_at' => now()->subMinutes(2)->toIso8601String()],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'recording');

        $trackId = $trackResponse->json('data.id');

        $appendResponse = $this->postJson("/api/v1/sport-tracks/{$trackId}/points", [
            'track_points' => [
                ['latitude' => 52.522, 'longitude' => 13.415, 'recorded_at' => now()->subMinute()->toIso8601String()],
                ['latitude' => 52.526, 'longitude' => 13.42, 'recorded_at' => now()->toIso8601String()],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.track_points.2.latitude', 52.526);

        $this->assertGreaterThan(0, $appendResponse->json('data.distance_meters'));

        $this->postJson("/api/v1/sport-tracks/{$trackId}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $placeResponse = $this->postJson('/api/v1/sport-places', [
            'name' => 'Community Laufbahn',
            'type' => 'running_track',
            'latitude' => 52.521,
            'longitude' => 13.407,
            'city' => 'Berlin',
            'country_code' => 'DE',
            'visibility' => 'public',
            'sport_types' => ['running'],
            'amenities' => ['lights'],
            'surfaces' => ['tartan'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Community Laufbahn')
            ->assertJsonPath('data.geojson.geometry.type', 'Point');

        $placeId = $placeResponse->json('data.id');

        SportPlace::query()->create([
            'user_id' => $user->id,
            'name' => 'Weite Laufbahn',
            'type' => 'running_track',
            'latitude' => 52.6,
            'longitude' => 13.5,
            'city' => 'Berlin',
            'country_code' => 'DE',
            'visibility' => 'public',
            'status' => 'active',
            'sport_types' => ['running'],
        ]);

        $nearbyResponse = $this->getJson('/api/v1/sport-places?latitude=52.5205&longitude=13.406&radius_km=5&type=running_track')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $placeId);

        $this->assertGreaterThan(0, $nearbyResponse->json('data.0.distance_meters'));

        $this->patchJson("/api/v1/sport-places/{$placeId}", [
            'description' => 'Abends beleuchtet.',
        ])
            ->assertOk()
            ->assertJsonPath('data.description', 'Abends beleuchtet.');

        $this->deleteJson("/api/v1/sport-tracks/{$trackId}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertSoftDeleted('sport_route_tracks', ['id' => $trackId]);
        $this->assertDatabaseHas('sport_places', ['id' => $placeId, 'name' => 'Community Laufbahn']);
    }
}
