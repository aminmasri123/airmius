<?php

namespace Tests\Feature;

use App\Models\SportPlace;
use App\Models\SportRoute;
use App\Models\SportRouteTrack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
                'gallery_images' => ['https://example.test/sportpark.jpg'],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('sport_places', [
            'name' => 'Sportpark Mitte',
            'type' => 'football_pitch',
            'status' => 'active',
        ]);
        $this->assertSame(['https://example.test/sportpark.jpg'], SportPlace::query()->where('name', 'Sportpark Mitte')->firstOrFail()->gallery_images);
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
            'gallery_images' => ['https://example.test/laufbahn.webp'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Community Laufbahn')
            ->assertJsonPath('data.gallery_images.0', 'https://example.test/laufbahn.webp')
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

    public function test_route_planning_can_use_configured_osrm_routing_provider(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);
        config()->set('sport_map.routing.provider', 'osrm');
        config()->set('sport_map.routing.osrm_base_url', 'https://osrm.test');
        config()->set('sport_map.routing.profiles.running', 'foot');

        Http::fake([
            'osrm.test/route/v1/foot/*' => Http::response([
                'routes' => [[
                    'distance' => 1234.5,
                    'duration' => 612.3,
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => [
                            [13.405, 52.52],
                            [13.412, 52.523],
                            [13.42, 52.526],
                        ],
                    ],
                    'legs' => [[
                        'steps' => [
                            [
                                'distance' => 500,
                                'duration' => 240,
                                'name' => 'Parkweg',
                                'maneuver' => [
                                    'type' => 'depart',
                                    'bearing_after' => 45,
                                    'location' => [13.405, 52.52],
                                ],
                            ],
                            [
                                'distance' => 734.5,
                                'duration' => 372,
                                'name' => 'Uferweg',
                                'maneuver' => [
                                    'type' => 'turn',
                                    'modifier' => 'right',
                                    'bearing_after' => 90,
                                    'location' => [13.412, 52.523],
                                ],
                            ],
                            [
                                'distance' => 0,
                                'duration' => 0,
                                'name' => '',
                                'maneuver' => [
                                    'type' => 'arrive',
                                    'bearing_after' => 0,
                                    'location' => [13.42, 52.526],
                                ],
                            ],
                        ],
                    ]],
                ]],
            ], 200),
        ]);

        $this->postJson('/api/v1/sport-routes', [
            'title' => 'OSRM Laufroute',
            'sport_type' => 'running',
            'visibility' => 'public',
            'waypoints' => [
                ['name' => 'Start', 'latitude' => 52.52, 'longitude' => 13.405],
                ['name' => 'Ziel', 'latitude' => 52.526, 'longitude' => 13.42],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.distance_meters', 1235)
            ->assertJsonPath('data.estimated_duration_seconds', 612)
            ->assertJsonPath('data.metrics.calculation', 'osrm_route_v1')
            ->assertJsonPath('data.metrics.routing_provider', 'osrm')
            ->assertJsonPath('data.metrics.routing_profile', 'foot')
            ->assertJsonPath('data.metrics.routing_status', 'routed')
            ->assertJsonPath('data.navigation_cues.1.type', 'turn')
            ->assertJsonPath('data.route_geometry.coordinates.0.0', 13.405);

        Http::assertSent(fn ($request) => str_contains(
            (string) $request->url(),
            '/route/v1/foot/13.405,52.52;13.42,52.526',
        ));
    }

    public function test_web_route_generator_returns_routed_geometry_for_preview(): void
    {
        $user = User::factory()->create();

        config()->set('sport_map.routing.route_generator_provider', 'osrm');
        config()->set('sport_map.routing.osrm_base_url', 'https://osrm.test');
        config()->set('sport_map.routing.profiles.running', 'foot');

        Http::fake([
            'osrm.test/route/v1/foot/*' => Http::response([
                'routes' => [[
                    'distance' => 5012,
                    'duration' => 1820,
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => [
                            [13.405, 52.52],
                            [13.407, 52.521],
                            [13.41, 52.523],
                            [13.405, 52.52],
                        ],
                    ],
                    'legs' => [[
                        'steps' => [[
                            'distance' => 1200,
                            'duration' => 420,
                            'name' => 'Parkweg',
                            'maneuver' => [
                                'type' => 'depart',
                                'bearing_after' => 30,
                                'location' => [13.405, 52.52],
                            ],
                        ]],
                    ]],
                ]],
            ], 200),
        ]);

        $this->actingAs($user)
            ->postJson(route('auth.sport-route-proposals.store'), [
                'sport_type' => 'running',
                'route_type' => 'roundtrip',
                'target_mode' => 'distance',
                'distance_km' => 5,
                'surface' => 'forest',
                'environment' => 'forest',
                'elevation' => 'mixed',
                'difficulty' => 'easy',
                'low_traffic' => true,
                'start' => [
                    'latitude' => 52.52,
                    'longitude' => 13.405,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.distance_meters', 5012)
            ->assertJsonPath('data.metrics.routing_status', 'routed')
            ->assertJsonPath('data.metrics.routing_provider', 'osrm')
            ->assertJsonPath('data.metrics.generator_parameters.surface', 'forest')
            ->assertJsonPath('data.metrics.quality.label', 'Sehr gut')
            ->assertJsonPath('data.navigation_cues.0.type', 'start')
            ->assertJsonCount(4, 'data.route_geometry.coordinates');
    }

    public function test_web_route_generator_changes_control_points_for_new_variants(): void
    {
        $user = User::factory()->create();

        config()->set('sport_map.routing.route_generator_provider', 'osrm');
        config()->set('sport_map.routing.osrm_base_url', 'https://osrm.test');

        Http::fake([
            'osrm.test/route/v1/foot/*' => Http::response([
                'routes' => [[
                    'distance' => 5000,
                    'duration' => 1800,
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => [
                            [13.405, 52.52],
                            [13.408, 52.522],
                            [13.405, 52.52],
                        ],
                    ],
                    'legs' => [],
                ]],
            ], 200),
        ]);

        $payload = [
            'sport_type' => 'running',
            'route_type' => 'roundtrip',
            'target_mode' => 'distance',
            'distance_km' => 5,
            'surface' => 'forest',
            'environment' => 'forest',
            'elevation' => 'mixed',
            'difficulty' => 'easy',
            'low_traffic' => true,
            'start' => [
                'latitude' => 52.52,
                'longitude' => 13.405,
            ],
        ];

        $first = $this->actingAs($user)
            ->postJson(route('auth.sport-route-proposals.store'), $payload + ['variant_seed' => 1001])
            ->assertOk()
            ->json('data.waypoints.1');

        $second = $this->actingAs($user)
            ->postJson(route('auth.sport-route-proposals.store'), $payload + ['variant_seed' => 2002])
            ->assertOk()
            ->json('data.waypoints.1');

        $this->assertNotSame($first['latitude'], $second['latitude']);
        $this->assertNotSame($first['longitude'], $second['longitude']);
    }

    public function test_route_generator_calibrates_roundtrip_distance_towards_target(): void
    {
        $user = User::factory()->create();
        $calls = 0;

        config()->set('sport_map.routing.route_generator_provider', 'osrm');
        config()->set('sport_map.routing.osrm_base_url', 'https://osrm.test');

        Http::fake(function () use (&$calls) {
            $calls++;
            $distance = $calls === 1 ? 12050 : 5100;

            return Http::response([
                'routes' => [[
                    'distance' => $distance,
                    'duration' => 1500,
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => [
                            [7.0676, 49.1122],
                            [7.071, 49.116],
                            [7.064, 49.116],
                            [7.0676, 49.1122],
                        ],
                    ],
                    'legs' => [],
                ]],
            ], 200);
        });

        $this->actingAs($user)
            ->postJson(route('auth.sport-route-proposals.store'), [
                'sport_type' => 'running',
                'route_type' => 'roundtrip',
                'target_mode' => 'distance',
                'distance_km' => 5,
                'surface' => 'firm',
                'environment' => 'park',
                'elevation' => 'flat',
                'difficulty' => 'easy',
                'variant_seed' => 4100,
                'start' => [
                    'latitude' => 49.1122,
                    'longitude' => 7.0676,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.distance_meters', 5100)
            ->assertJsonPath('data.metrics.target_distance_meters', 5000)
            ->assertJsonPath('data.metrics.target_delta_meters', 100)
            ->assertJsonPath('data.metrics.calibration_attempts', 2);

        $this->assertSame(2, $calls);
    }

    public function test_roundtrip_generator_keeps_start_on_loop_edge_instead_of_center(): void
    {
        $user = User::factory()->create();

        config()->set('sport_map.routing.route_generator_provider', 'local');

        $waypoints = $this->actingAs($user)
            ->postJson(route('auth.sport-route-proposals.store'), [
                'sport_type' => 'running',
                'route_type' => 'roundtrip',
                'target_mode' => 'distance',
                'distance_km' => 5,
                'surface' => 'forest',
                'environment' => 'forest',
                'elevation' => 'mixed',
                'difficulty' => 'easy',
                'low_traffic' => true,
                'variant_seed' => 3003,
                'start' => [
                    'latitude' => 49.1122,
                    'longitude' => 7.0676,
                ],
            ])
            ->assertOk()
            ->json('data.waypoints');

        $start = $waypoints[0];
        $last = $waypoints[count($waypoints) - 1];

        $this->assertSame($start['latitude'], $last['latitude']);
        $this->assertSame($start['longitude'], $last['longitude']);
        $this->assertCount(4, $waypoints);

        for ($index = 2; $index <= count($waypoints) - 2; $index++) {
            $this->assertGreaterThan(
                650,
                $this->distanceFromPointToSegmentMeters($start, $waypoints[$index - 1], $waypoints[$index]),
                'Rundroute-Segmente duerfen nicht durch den Startpunkt zurueckschneiden.',
            );
        }
    }

    public function test_roundtrip_generator_retries_when_route_goes_out_and_back(): void
    {
        $user = User::factory()->create();
        $calls = 0;

        config()->set('sport_map.routing.route_generator_provider', 'osrm');
        config()->set('sport_map.routing.osrm_base_url', 'https://osrm.test');

        Http::fake(function () use (&$calls) {
            $calls++;
            $outAndBack = [
                [7.0676, 49.1122],
                [7.0776, 49.1122],
                [7.0876, 49.1122],
                [7.0776, 49.1122],
                [7.0676, 49.1122],
            ];
            $loop = [
                [7.0676, 49.1122],
                [7.0776, 49.1122],
                [7.0776, 49.1222],
                [7.0676, 49.1222],
                [7.0676, 49.1122],
            ];

            return Http::response([
                'routes' => [[
                    'distance' => $calls === 1 ? 5000 : 5050,
                    'duration' => 1800,
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => $calls === 1 ? $outAndBack : $loop,
                    ],
                    'legs' => [],
                ]],
            ], 200);
        });

        $this->actingAs($user)
            ->postJson(route('auth.sport-route-proposals.store'), [
                'sport_type' => 'running',
                'route_type' => 'roundtrip',
                'target_mode' => 'distance',
                'distance_km' => 5,
                'surface' => 'firm',
                'environment' => 'park',
                'elevation' => 'flat',
                'difficulty' => 'easy',
                'variant_seed' => 5200,
                'start' => [
                    'latitude' => 49.1122,
                    'longitude' => 7.0676,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.distance_meters', 5050)
            ->assertJsonPath('data.metrics.route_shape.acceptable', true)
            ->assertJsonPath('data.metrics.quality.shape_acceptable', true)
            ->assertJsonPath('data.metrics.calibration_attempts', 2);

        $this->assertSame(2, $calls);
    }

    public function test_route_generator_can_use_explicit_destination_for_one_way_route(): void
    {
        $user = User::factory()->create();

        config()->set('sport_map.routing.route_generator_provider', 'osrm');
        config()->set('sport_map.routing.osrm_base_url', 'https://osrm.test');

        Http::fake([
            'osrm.test/route/v1/foot/*' => Http::response([
                'routes' => [[
                    'distance' => 4300,
                    'duration' => 1500,
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => [
                            [7.0676, 49.1122],
                            [7.081, 49.119],
                            [7.096, 49.125],
                        ],
                    ],
                    'legs' => [],
                ]],
            ], 200),
        ]);

        $this->actingAs($user)
            ->postJson(route('auth.sport-route-proposals.store'), [
                'sport_type' => 'running',
                'route_type' => 'point_to_point',
                'target_mode' => 'distance',
                'distance_km' => 5,
                'surface' => 'firm',
                'environment' => 'park',
                'elevation' => 'flat',
                'difficulty' => 'easy',
                'waypoints' => [
                    [
                        'name' => 'Start',
                        'latitude' => 49.1122,
                        'longitude' => 7.0676,
                    ],
                    [
                        'name' => 'Ziel',
                        'latitude' => 49.125,
                        'longitude' => 7.096,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.route_type', 'point_to_point')
            ->assertJsonPath('data.waypoints.0.name', 'Start')
            ->assertJsonPath('data.waypoints.1.name', 'Ziel')
            ->assertJsonPath('data.distance_meters', 4300)
            ->assertJsonPath('data.metrics.quality.uses_real_routing', true);
    }

    public function test_point_to_point_generator_creates_one_way_route_without_return_to_start(): void
    {
        $user = User::factory()->create();

        config()->set('sport_map.routing.route_generator_provider', 'local');

        $waypoints = $this->actingAs($user)
            ->postJson(route('auth.sport-route-proposals.store'), [
                'sport_type' => 'running',
                'route_type' => 'point_to_point',
                'target_mode' => 'distance',
                'distance_km' => 5,
                'surface' => 'firm',
                'environment' => 'park',
                'elevation' => 'flat',
                'difficulty' => 'easy',
                'variant_seed' => 6100,
                'start' => [
                    'latitude' => 49.1122,
                    'longitude' => 7.0676,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.route_type', 'point_to_point')
            ->assertJsonPath('data.waypoints.2.name', 'Ziel')
            ->json('data.waypoints');

        $this->assertCount(3, $waypoints);
        $this->assertGreaterThan(3500, $this->distanceBetweenPointsMeters($waypoints[0], $waypoints[2]));
    }

    private function distanceBetweenPointsMeters(array $from, array $to): float
    {
        return sqrt(array_sum(array_map(
            fn ($value) => $value ** 2,
            [
                $this->projectForDistance($to, $from)[0],
                $this->projectForDistance($to, $from)[1],
            ],
        )));
    }

    private function distanceFromPointToSegmentMeters(array $point, array $from, array $to): float
    {
        [$px, $py] = $this->projectForDistance($point, $point);
        [$ax, $ay] = $this->projectForDistance($from, $point);
        [$bx, $by] = $this->projectForDistance($to, $point);
        $dx = $bx - $ax;
        $dy = $by - $ay;
        $lengthSquared = ($dx * $dx) + ($dy * $dy);

        if ($lengthSquared <= 0) {
            return sqrt((($px - $ax) ** 2) + (($py - $ay) ** 2));
        }

        $t = max(0, min(1, ((($px - $ax) * $dx) + (($py - $ay) * $dy)) / $lengthSquared));
        $nearestX = $ax + ($t * $dx);
        $nearestY = $ay + ($t * $dy);

        return sqrt((($px - $nearestX) ** 2) + (($py - $nearestY) ** 2));
    }

    private function projectForDistance(array $point, array $origin): array
    {
        $latitude = (float) $point['latitude'];
        $longitude = (float) $point['longitude'];
        $originLatitude = (float) $origin['latitude'];
        $originLongitude = (float) $origin['longitude'];

        return [
            ($longitude - $originLongitude) * 111320 * cos(deg2rad($originLatitude)),
            ($latitude - $originLatitude) * 110540,
        ];
    }
}
