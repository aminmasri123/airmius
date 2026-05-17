<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class SportRouteRoutingService
{
    private const GENERATOR_SPEEDS_KMH = [
        'walking' => 5.0,
        'wandern' => 4.5,
        'running' => 9.0,
        'trail_running' => 7.0,
        'cycling' => 18.0,
        'mountainbike' => 14.0,
        'skateboard' => 10.0,
        'fitness' => 6.0,
        'football' => 6.0,
        'other' => 7.0,
    ];

    private const ENVIRONMENT_BEARINGS = [
        'nature' => 45,
        'forest' => 315,
        'park' => 90,
        'water' => 135,
    ];

    private const SURFACE_BEARING_OFFSETS = [
        'any' => 0,
        'asphalt' => 8,
        'firm' => 16,
        'forest' => -22,
        'gravel' => 28,
        'trail' => -34,
    ];

    public function __construct(private readonly SportRouteMetricService $metrics)
    {
    }

    public function summarizeRoute(array $points, ?string $sportType = null): array
    {
        return $this->routedSummary($points, $sportType);
    }

    public function generateProposal(array $data): array
    {
        $sportType = $data['sport_type'] ?? 'running';
        $waypoints = isset($data['waypoints']) && is_array($data['waypoints']) && count($data['waypoints']) >= 2
            ? $this->metrics->normalizePoints($data['waypoints'])
            : $this->proposalWaypoints($data);
        $summary = $this->routedSummary($waypoints, $sportType, true);

        return [
            'title' => trim((string) ($data['title'] ?? '')) !== '' ? trim((string) $data['title']) : $this->proposalTitle($data),
            'sport_type' => $sportType,
            'route_type' => $data['route_type'] ?? 'roundtrip',
            'surface' => $data['surface'] ?? 'any',
            'environment' => $data['environment'] ?? 'nature',
            'difficulty' => $data['difficulty'] ?? 'easy',
            'waypoints' => $waypoints,
            'route_geometry' => $summary['geometry'],
            'navigation_cues' => $summary['navigation_cues'],
            'distance_meters' => $summary['distance_meters'],
            'estimated_duration_seconds' => $summary['estimated_duration_seconds'],
            'elevation_gain_meters' => $summary['elevation_gain_meters'],
            'elevation_loss_meters' => $summary['elevation_loss_meters'],
            'metrics' => array_filter([
                'bounds' => $summary['bounds'],
                'calculation' => $summary['calculation'] ?? null,
                'routing_provider' => $summary['routing_provider'] ?? null,
                'routing_profile' => $summary['routing_profile'] ?? null,
                'routing_status' => $summary['routing_status'] ?? null,
                'routing_error' => $summary['routing_error'] ?? null,
                'generator_parameters' => $this->generatorParameters($data),
                'geometry_point_count' => count(data_get($summary, 'geometry.coordinates', [])),
            ], fn ($value) => $value !== null && $value !== ''),
        ];
    }

    private function routedSummary(array $points, ?string $sportType = null, bool $forceRouting = false): array
    {
        $fallback = $this->localSummary($points, $sportType);

        if (count($points) < 2 || (! $forceRouting && $this->provider() !== 'osrm') || ($forceRouting && $this->proposalProvider() !== 'osrm')) {
            return $fallback;
        }

        $profile = $this->profileFor($sportType);

        try {
            $route = $this->fetchOsrmRoute($points, $profile);

            if (! $route) {
                return $this->osrmFallback($fallback, $profile, 'empty_route');
            }

            return $this->summaryFromOsrmRoute($route, $fallback, $profile);
        } catch (Throwable $exception) {
            return $this->osrmFallback($fallback, $profile, class_basename($exception));
        }
    }

    private function proposalWaypoints(array $data): array
    {
        $start = [
            'name' => 'Start',
            'latitude' => (float) data_get($data, 'start.latitude'),
            'longitude' => (float) data_get($data, 'start.longitude'),
        ];
        $distanceMeters = $this->targetDistanceMeters($data);
        $routeType = $data['route_type'] ?? 'roundtrip';
        $baseBearing = $this->proposalBearing($data);

        if ($routeType === 'roundtrip') {
            $spread = match ($data['elevation'] ?? 'mixed') {
                'flat' => 62,
                'hilly' => 112,
                default => 88,
            };
            $difficultyShift = match ($data['difficulty'] ?? 'easy') {
                'hard' => 24,
                'moderate' => 12,
                default => 0,
            };
            $legDistance = max(350, $distanceMeters / 3.8);

            $points = [
                $start,
                $this->coordinateAtDistanceBearing($start, $legDistance, $baseBearing + $difficultyShift),
                $this->coordinateAtDistanceBearing($start, $legDistance * 1.16, $baseBearing + $spread),
                $this->coordinateAtDistanceBearing($start, $legDistance * 0.92, $baseBearing + ($spread * 2) - $difficultyShift),
                $start,
            ];
        } else {
            $points = [
                $start,
                $this->coordinateAtDistanceBearing($start, $distanceMeters * 0.5, $baseBearing - 10),
                $this->coordinateAtDistanceBearing($start, $distanceMeters, $baseBearing + 12),
            ];
        }

        return $this->metrics->normalizePoints(collect($points)
            ->map(function (array $point, int $index) use ($routeType, $points) {
                $isLast = $index === count($points) - 1;

                return [
                    'name' => $this->proposalWaypointName($index, $isLast, $routeType),
                    'latitude' => $point['latitude'],
                    'longitude' => $point['longitude'],
                ];
            })
            ->all());
    }

    private function targetDistanceMeters(array $data): int
    {
        if (($data['target_mode'] ?? 'distance') === 'duration') {
            $speed = self::GENERATOR_SPEEDS_KMH[$data['sport_type'] ?? 'running'] ?? self::GENERATOR_SPEEDS_KMH['other'];
            $minutes = $this->clamp((float) ($data['duration_minutes'] ?? 45), 10, 600);

            return (int) round($this->clamp(($minutes / 60) * $speed, 1, 80) * 1000);
        }

        return (int) round($this->clamp((float) ($data['distance_km'] ?? 5), 1, 80) * 1000);
    }

    private function proposalBearing(array $data): float
    {
        $environment = (string) ($data['environment'] ?? 'nature');
        $surface = (string) ($data['surface'] ?? 'any');
        $bearing = self::ENVIRONMENT_BEARINGS[$environment] ?? self::ENVIRONMENT_BEARINGS['nature'];
        $bearing += self::SURFACE_BEARING_OFFSETS[$surface] ?? 0;

        if (($data['low_traffic'] ?? false) === true) {
            $bearing -= 14;
        }

        if (($data['water_breaks'] ?? false) === true) {
            $bearing += 10;
        }

        return fmod($bearing + 360, 360);
    }

    private function proposalWaypointName(int $index, bool $isLast, string $routeType): string
    {
        if ($index === 0) {
            return 'Start';
        }

        if ($isLast) {
            return $routeType === 'roundtrip' ? 'Zurueck zum Start' : 'Ziel';
        }

        return 'Routenpunkt '.$index;
    }

    private function coordinateAtDistanceBearing(array $origin, float $distanceMeters, float $bearingDegrees): array
    {
        $earthRadius = 6371000;
        $bearing = deg2rad($bearingDegrees);
        $lat1 = deg2rad((float) $origin['latitude']);
        $lon1 = deg2rad((float) $origin['longitude']);
        $angularDistance = $distanceMeters / $earthRadius;

        $lat2 = asin(
            sin($lat1) * cos($angularDistance)
            + cos($lat1) * sin($angularDistance) * cos($bearing)
        );
        $lon2 = $lon1 + atan2(
            sin($bearing) * sin($angularDistance) * cos($lat1),
            cos($angularDistance) - sin($lat1) * sin($lat2)
        );

        return [
            'latitude' => round(rad2deg($lat2), 7),
            'longitude' => round(rad2deg($lon2), 7),
        ];
    }

    private function proposalTitle(array $data): string
    {
        $distance = round($this->targetDistanceMeters($data) / 1000, 1);

        return (($data['route_type'] ?? 'roundtrip') === 'roundtrip' ? 'Rundroute' : 'Zielroute').' '.$distance.' km';
    }

    private function generatorParameters(array $data): array
    {
        return [
            'target_mode' => $data['target_mode'] ?? 'distance',
            'distance_km' => $this->targetDistanceMeters($data) / 1000,
            'surface' => $data['surface'] ?? 'any',
            'environment' => $data['environment'] ?? 'nature',
            'elevation' => $data['elevation'] ?? 'mixed',
            'difficulty' => $data['difficulty'] ?? 'easy',
            'low_traffic' => (bool) ($data['low_traffic'] ?? false),
            'lit' => (bool) ($data['lit'] ?? false),
            'water_breaks' => (bool) ($data['water_breaks'] ?? false),
            'include_places' => $data['include_places'] ?? null,
            'avoid_places' => $data['avoid_places'] ?? null,
        ];
    }

    private function clamp(float $value, float $min, float $max): float
    {
        return min(max($value, $min), $max);
    }

    private function localSummary(array $points, ?string $sportType): array
    {
        return $this->metrics->summarize($points, $sportType) + [
            'calculation' => 'airmius_haversine_estimate',
            'routing_provider' => 'local',
            'routing_profile' => null,
            'routing_status' => 'estimated',
        ];
    }

    private function fetchOsrmRoute(array $points, string $profile): ?array
    {
        $coordinates = collect($points)
            ->map(fn (array $point) => (float) $point['longitude'].','.(float) $point['latitude'])
            ->implode(';');
        $baseUrl = rtrim((string) config('sport_map.routing.osrm_base_url', 'https://router.project-osrm.org'), '/');

        $response = Http::timeout(max(1, (int) config('sport_map.routing.timeout_seconds', 4)))
            ->acceptJson()
            ->get("{$baseUrl}/route/v1/{$profile}/{$coordinates}", [
                'overview' => 'full',
                'geometries' => 'geojson',
                'steps' => 'true',
            ]);

        if (! $response->successful()) {
            return null;
        }

        $route = data_get($response->json(), 'routes.0');

        return is_array($route) ? $route : null;
    }

    private function summaryFromOsrmRoute(array $route, array $fallback, string $profile): array
    {
        $coordinates = collect(data_get($route, 'geometry.coordinates', []))
            ->filter(fn ($coordinate) => is_array($coordinate) && count($coordinate) >= 2)
            ->map(fn (array $coordinate) => [
                round((float) $coordinate[0], 7),
                round((float) $coordinate[1], 7),
            ])
            ->values()
            ->all();

        if (count($coordinates) < 2) {
            return $this->osrmFallback($fallback, $profile, 'invalid_geometry');
        }

        $distanceMeters = (int) round((float) data_get($route, 'distance', $fallback['distance_meters']));
        $durationSeconds = (int) round((float) data_get($route, 'duration', $fallback['estimated_duration_seconds']));

        return array_replace($fallback, [
            'distance_meters' => $distanceMeters,
            'duration_seconds' => $durationSeconds,
            'estimated_duration_seconds' => $durationSeconds,
            'average_speed_mps' => $durationSeconds > 0 ? round($distanceMeters / $durationSeconds, 3) : null,
            'bounds' => $this->boundsFromCoordinates($coordinates) ?? $fallback['bounds'],
            'geometry' => [
                'type' => 'LineString',
                'coordinates' => $coordinates,
            ],
            'navigation_cues' => $this->navigationCuesFromLegs(data_get($route, 'legs', []), $fallback['navigation_cues']),
            'calculation' => 'osrm_route_v1',
            'routing_provider' => 'osrm',
            'routing_profile' => $profile,
            'routing_status' => 'routed',
        ]);
    }

    private function navigationCuesFromLegs(array $legs, array $fallback): array
    {
        $cues = [];

        foreach ($legs as $leg) {
            foreach ((array) data_get($leg, 'steps', []) as $step) {
                if (! is_array($step)) {
                    continue;
                }

                $maneuver = (array) data_get($step, 'maneuver', []);
                $location = data_get($maneuver, 'location');

                $cues[] = array_filter([
                    'type' => $this->cueType((string) data_get($maneuver, 'type', 'continue')),
                    'maneuver_type' => data_get($maneuver, 'type') ?: 'continue',
                    'road_name' => data_get($step, 'name') ?: null,
                    'distance_meters' => (int) round((float) data_get($step, 'distance', 0)),
                    'duration_seconds' => (int) round((float) data_get($step, 'duration', 0)),
                    'bearing_degrees' => data_get($maneuver, 'bearing_after') !== null ? (int) round((float) data_get($maneuver, 'bearing_after')) : null,
                    'location' => is_array($location) && count($location) >= 2 ? [
                        'latitude' => round((float) $location[1], 7),
                        'longitude' => round((float) $location[0], 7),
                    ] : null,
                    'modifier' => data_get($maneuver, 'modifier') ?: null,
                ], fn ($value) => $value !== null && $value !== '');
            }
        }

        return $cues !== [] ? $cues : $fallback;
    }

    private function cueType(string $type): string
    {
        return match ($type) {
            'depart' => 'start',
            'arrive' => 'finish',
            default => $type ?: 'continue',
        };
    }

    private function boundsFromCoordinates(array $coordinates): ?array
    {
        if ($coordinates === []) {
            return null;
        }

        $latitudes = array_map(fn (array $coordinate) => (float) $coordinate[1], $coordinates);
        $longitudes = array_map(fn (array $coordinate) => (float) $coordinate[0], $coordinates);

        return [
            'north' => max($latitudes),
            'south' => min($latitudes),
            'east' => max($longitudes),
            'west' => min($longitudes),
        ];
    }

    private function osrmFallback(array $fallback, string $profile, string $reason): array
    {
        return array_replace($fallback, [
            'routing_provider' => 'osrm',
            'routing_profile' => $profile,
            'routing_status' => 'fallback',
            'routing_error' => substr($reason, 0, 120),
        ]);
    }

    private function provider(): string
    {
        return strtolower((string) config('sport_map.routing.provider', 'local'));
    }

    private function proposalProvider(): string
    {
        return strtolower((string) config('sport_map.routing.route_generator_provider', 'osrm'));
    }

    private function profileFor(?string $sportType): string
    {
        $profiles = config('sport_map.routing.profiles', []);
        $key = strtolower((string) $sportType);

        return (string) ($profiles[$key] ?? $profiles['other'] ?? 'foot');
    }
}
