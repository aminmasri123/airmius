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

    public function __construct(
        private readonly SportRouteMetricService $metrics,
        private readonly ExternalProviderUsageService $usage,
    )
    {
    }

    public function summarizeRoute(array $points, ?string $sportType = null): array
    {
        return $this->routedSummary($points, $sportType);
    }

    public function generateProposal(array $data): array
    {
        $sportType = $data['sport_type'] ?? 'running';
        $calibration = [
            'target_distance_meters' => $this->targetDistanceMeters($data),
            'attempts' => 1,
            'radius_scale' => 1.0,
        ];

        if (isset($data['waypoints']) && is_array($data['waypoints']) && count($data['waypoints']) >= 2) {
            $waypoints = $this->metrics->normalizePoints($data['waypoints']);
            $summary = $this->routedSummary($waypoints, $sportType, true);
        } else {
            [$waypoints, $summary, $calibration] = $this->generatedRouteCandidate($data, $sportType);
        }

        $routeShape = $calibration['shape'] ?? $this->routeShapeMetrics($summary, ($data['route_type'] ?? 'roundtrip') === 'roundtrip');
        $quality = $this->routeQualityMetrics($summary, $data, $routeShape, $calibration);

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
                'target_distance_meters' => $calibration['target_distance_meters'] ?? null,
                'target_delta_meters' => isset($calibration['target_distance_meters'])
                    ? abs((int) $summary['distance_meters'] - (int) $calibration['target_distance_meters'])
                    : null,
                'calibration_attempts' => $calibration['attempts'] ?? null,
                'calibration_radius_scale' => $calibration['radius_scale'] ?? null,
                'route_shape' => $routeShape,
                'quality' => $quality,
                'geometry_point_count' => count(data_get($summary, 'geometry.coordinates', [])),
            ], fn ($value) => $value !== null && $value !== ''),
        ];
    }

    private function generatedRouteCandidate(array $data, string $sportType): array
    {
        if (($data['route_type'] ?? 'roundtrip') !== 'roundtrip') {
            $waypoints = $this->proposalWaypoints($data);
            $summary = $this->routedSummary($waypoints, $sportType, true);

            return [$waypoints, $summary, [
                'target_distance_meters' => $this->targetDistanceMeters($data),
                'attempts' => 1,
                'radius_scale' => 1.0,
            ]];
        }

        if ($this->proposalProvider() === 'graphhopper') {
            $roundTrip = $this->graphHopperRoundTripCandidate($data, $sportType);

            if ($roundTrip !== null) {
                return $roundTrip;
            }
        }

        return $this->calibratedRoundtripProposal($data, $sportType);
    }

    private function graphHopperRoundTripCandidate(array $data, string $sportType): ?array
    {
        $start = [
            'name' => 'Start',
            'latitude' => (float) data_get($data, 'start.latitude'),
            'longitude' => (float) data_get($data, 'start.longitude'),
        ];
        $targetDistance = $this->targetDistanceMeters($data);
        $profile = $this->profileFor('graphhopper', $sportType);
        $variantSeed = $this->variantSeed($data);

        try {
            $route = $this->fetchGraphHopperRoundTripRoute($start, $profile, $targetDistance, $variantSeed, $this->proposalBearing($data, $variantSeed));

            if (! $route) {
                return null;
            }

            $summary = $this->summaryFromExternalRoute($route, $this->localSummary([$start, $start], $sportType), 'graphhopper', $profile);
            $waypoints = $this->waypointsFromRouteGeometry($summary, $start);

            return [$waypoints, $summary, [
                'target_distance_meters' => $targetDistance,
                'attempts' => 1,
                'radius_scale' => 1.0,
                'shape' => $this->routeShapeMetrics($summary, true),
                'routing_algorithm' => 'graphhopper_round_trip',
            ]];
        } catch (Throwable $exception) {
            $this->recordRoutingUsage('graphhopper', $profile, 'roundtrip_exception', [
                'error' => class_basename($exception),
                'waypoints' => 1,
            ]);

            return null;
        }
    }

    private function calibratedRoundtripProposal(array $data, string $sportType): array
    {
        $targetDistance = $this->targetDistanceMeters($data);
        $baseVariantSeed = $this->variantSeed($data);
        $scale = 1.0;
        $best = null;
        $attempts = 0;

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $candidateData = $data;
            $candidateData['variant_seed'] = $baseVariantSeed + (($attempt - 1) * 104729);
            $waypoints = $this->proposalWaypoints($candidateData, $scale);
            $summary = $this->routedSummary($waypoints, $sportType, true);
            $actualDistance = max(1, (int) $summary['distance_meters']);
            $delta = abs($actualDistance - $targetDistance);
            $shapeMetrics = $this->routeShapeMetrics($summary, true);
            $shapePenalty = (int) round(($shapeMetrics['backtrack_ratio'] ?? 0) * $targetDistance * 2.2);
            $score = $delta + $shapePenalty;
            $attempts = $attempt;

            if (! $best || $score < $best['score']) {
                $best = [
                    'waypoints' => $waypoints,
                    'summary' => $summary,
                    'delta' => $delta,
                    'score' => $score,
                    'scale' => $scale,
                    'shape_metrics' => $shapeMetrics,
                ];
            }

            if ($this->routeShapeIsAcceptable($shapeMetrics, true, $targetDistance)
                && $delta <= max(180, (int) round($targetDistance * 0.08))) {
                break;
            }

            $scale *= $this->clamp($targetDistance / $actualDistance, 0.35, 1.65);
            $scale = $this->clamp($scale, 0.28, 1.8);
        }

        return [$best['waypoints'], $best['summary'], [
            'target_distance_meters' => $targetDistance,
            'attempts' => $attempts,
            'radius_scale' => round((float) $best['scale'], 3),
            'shape' => $best['shape_metrics'] ?? null,
        ]];
    }

    private function routedSummary(array $points, ?string $sportType = null, bool $forceRouting = false): array
    {
        $fallback = $this->localSummary($points, $sportType);
        $provider = $forceRouting ? $this->proposalProvider() : $this->provider();

        if (count($points) < 2 || ! in_array($provider, ['osrm', 'mapbox', 'graphhopper'], true)) {
            return $fallback;
        }

        $profile = $this->profileFor($provider, $sportType);

        try {
            $route = match ($provider) {
                'mapbox' => $this->fetchMapboxRoute($points, $profile),
                'graphhopper' => $this->fetchGraphHopperRoute($points, $profile),
                default => $this->fetchOsrmRoute($points, $profile),
            };

            if (! $route) {
                return $this->externalRoutingFallback($fallback, $provider, $profile, 'empty_route');
            }

            return $this->summaryFromExternalRoute($route, $fallback, $provider, $profile);
        } catch (Throwable $exception) {
            $this->recordRoutingUsage($provider, $profile, 'exception', [
                'error' => class_basename($exception),
                'waypoints' => count($points),
            ]);

            return $this->externalRoutingFallback($fallback, $provider, $profile, class_basename($exception));
        }
    }

    private function proposalWaypoints(array $data, float $radiusScale = 1.0): array
    {
        $start = [
            'name' => 'Start',
            'latitude' => (float) data_get($data, 'start.latitude'),
            'longitude' => (float) data_get($data, 'start.longitude'),
        ];
        $distanceMeters = $this->targetDistanceMeters($data);
        $routeType = $data['route_type'] ?? 'roundtrip';
        $variantSeed = $this->variantSeed($data);
        $baseBearing = $this->proposalBearing($data, $variantSeed);

        if ($routeType === 'roundtrip') {
            $controlPointCount = $distanceMeters >= 12000 ? 4 : ($distanceMeters <= 7000 ? 2 : 3);
            $clockwise = $this->seededUnit($variantSeed, 2) > 0.5 ? 1 : -1;
            $loopRadius = max(220, (($distanceMeters / (2 * pi())) * (0.9 + $this->seededUnit($variantSeed, 3) * 0.2)) * $radiusScale);
            $difficultyScale = match ($data['difficulty'] ?? 'easy') {
                'hard' => 1.12,
                'moderate' => 1.05,
                default => 1.0,
            };
            $elevationSpread = match ($data['elevation'] ?? 'mixed') {
                'flat' => 0.92,
                'hilly' => 1.14,
                default => 1.0,
            };
            $loopRadius *= $difficultyScale * $elevationSpread;
            $loopCenter = $this->coordinateAtDistanceBearing($start, $loopRadius, $baseBearing);
            $startBearingFromCenter = fmod($baseBearing + 180, 360);
            $arcStep = 360 / ($controlPointCount + 1);
            $points = [$start];

            for ($index = 0; $index < $controlPointCount; $index++) {
                $angleJitter = ($this->seededUnit($variantSeed, 10 + $index) - 0.5) * 18;
                $radiusJitter = 0.92 + ($this->seededUnit($variantSeed, 20 + $index) * 0.18);
                $bearing = $startBearingFromCenter + ($clockwise * ($arcStep * ($index + 1) + $angleJitter));

                $points[] = $this->coordinateAtDistanceBearing($loopCenter, $loopRadius * $radiusJitter, $bearing);
            }

            $points[] = $start;
        } else {
            $finishBearing = $baseBearing + (($this->seededUnit($variantSeed, 4) - 0.5) * 70);
            $curveDirection = $this->seededUnit($variantSeed, 5) > 0.5 ? 1 : -1;
            $finishDistance = $distanceMeters * (0.9 + $this->seededUnit($variantSeed, 6) * 0.22);

            $points = [
                $start,
                $this->coordinateAtDistanceBearing($start, $finishDistance * 0.48, $finishBearing + ($curveDirection * (26 + $this->seededUnit($variantSeed, 7) * 28))),
                $this->coordinateAtDistanceBearing($start, $finishDistance, $finishBearing),
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

    private function variantSeed(array $data): int
    {
        $seed = (int) ($data['variant_seed'] ?? 0);

        return $seed > 0 ? $seed : random_int(1, PHP_INT_MAX);
    }

    private function seededUnit(int $seed, int $slot): float
    {
        $value = sin(($seed + 1) * (12.9898 + $slot) + ($slot * 78.233)) * 43758.5453;

        return $value - floor($value);
    }

    private function proposalBearing(array $data, int $variantSeed): float
    {
        $environment = (string) ($data['environment'] ?? 'nature');
        $surface = (string) ($data['surface'] ?? 'any');
        $bearing = $environment === 'any'
            ? $this->seededUnit($variantSeed, 30) * 360
            : (self::ENVIRONMENT_BEARINGS[$environment] ?? self::ENVIRONMENT_BEARINGS['nature']);
        $bearing += self::SURFACE_BEARING_OFFSETS[$surface] ?? 0;
        $bearing += ($this->seededUnit($variantSeed, 1) - 0.5) * 140;

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
            return $routeType === 'roundtrip' ? 'Zurück zum Start' : 'Ziel';
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

        return (($data['route_type'] ?? 'roundtrip') === 'roundtrip' ? 'Rundroute' : 'Einmal zum Ziel').' '.$distance.' km';
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
            'variant_seed' => $data['variant_seed'] ?? null,
        ];
    }

    private function clamp(float $value, float $min, float $max): float
    {
        return min(max($value, $min), $max);
    }

    private function routeShapeIsAcceptable(array $metrics, bool $roundtrip, int $targetDistanceMeters): bool
    {
        $backtrackRatio = (float) ($metrics['backtrack_ratio'] ?? 0);

        if ($backtrackRatio > ($roundtrip ? 0.18 : 0.12)) {
            return false;
        }

        if ($roundtrip) {
            $closureMeters = (float) ($metrics['loop_closure_meters'] ?? 0);

            return $closureMeters <= max(120, $targetDistanceMeters * 0.04);
        }

        return true;
    }

    private function routeShapeMetrics(array $summary, bool $roundtrip): array
    {
        $points = $this->geometryPointsFromSummary($summary);
        $distanceMeters = max(1, (int) ($summary['distance_meters'] ?? 0));
        $backtrackMeters = $this->backtrackMeters($points);
        $first = $points[0] ?? null;
        $last = $points[count($points) - 1] ?? null;
        $closureMeters = $roundtrip && $first && $last ? $this->distanceBetween($first, $last) : null;

        return array_filter([
            'mode' => $roundtrip ? 'roundtrip' : 'one_way',
            'backtrack_meters' => (int) round($backtrackMeters),
            'backtrack_ratio' => round($backtrackMeters / $distanceMeters, 3),
            'loop_closure_meters' => $closureMeters !== null ? (int) round($closureMeters) : null,
            'acceptable' => $this->routeShapeIsAcceptable([
                'backtrack_ratio' => $backtrackMeters / $distanceMeters,
                'loop_closure_meters' => $closureMeters,
            ], $roundtrip, $distanceMeters),
        ], fn ($value) => $value !== null);
    }

    private function routeQualityMetrics(array $summary, array $data, array $shapeMetrics, array $calibration): array
    {
        $targetDistance = max(1, (int) ($calibration['target_distance_meters'] ?? $this->targetDistanceMeters($data)));
        $actualDistance = max(1, (int) ($summary['distance_meters'] ?? 0));
        $targetDelta = abs($actualDistance - $targetDistance);
        $targetDeltaRatio = $targetDelta / $targetDistance;
        $backtrackRatio = (float) ($shapeMetrics['backtrack_ratio'] ?? 0);
        $score = 100;

        $score -= min(42, (int) round($targetDeltaRatio * 130));
        $score -= min(36, (int) round($backtrackRatio * 190));

        if (($summary['routing_status'] ?? null) !== 'routed') {
            $score -= 14;
        }

        if (($shapeMetrics['acceptable'] ?? true) !== true) {
            $score -= 18;
        }

        $score = (int) $this->clamp($score, 0, 100);

        return [
            'score' => $score,
            'label' => match (true) {
                $score >= 85 => 'Sehr gut',
                $score >= 70 => 'Gut',
                $score >= 50 => 'Prüfen',
                default => 'Schwach',
            },
            'target_delta_meters' => (int) round($targetDelta),
            'target_delta_percent' => round($targetDeltaRatio * 100, 1),
            'backtrack_percent' => round($backtrackRatio * 100, 1),
            'uses_real_routing' => ($summary['routing_status'] ?? null) === 'routed',
            'shape_acceptable' => (bool) ($shapeMetrics['acceptable'] ?? false),
        ];
    }

    private function geometryPointsFromSummary(array $summary): array
    {
        return collect(data_get($summary, 'geometry.coordinates', []))
            ->filter(fn ($coordinate) => is_array($coordinate) && count($coordinate) >= 2)
            ->map(fn (array $coordinate) => [
                'longitude' => (float) $coordinate[0],
                'latitude' => (float) $coordinate[1],
            ])
            ->values()
            ->all();
    }

    private function waypointsFromRouteGeometry(array $summary, array $start): array
    {
        $points = $this->geometryPointsFromSummary($summary);

        if (count($points) < 8) {
            return $this->metrics->normalizePoints([$start, $start]);
        }

        $sampleIndexes = [
            0,
            (int) floor((count($points) - 1) * 0.25),
            (int) floor((count($points) - 1) * 0.5),
            (int) floor((count($points) - 1) * 0.75),
            count($points) - 1,
        ];

        return $this->metrics->normalizePoints(collect($sampleIndexes)
            ->map(function (int $pointIndex, int $waypointIndex) use ($points, $start, $sampleIndexes) {
                $isFirst = $waypointIndex === 0;
                $isLast = $waypointIndex === count($sampleIndexes) - 1;
                $point = $points[$pointIndex] ?? $start;

                return [
                    'name' => $isFirst ? 'Start' : ($isLast ? 'Zurück zum Start' : 'Rundenpunkt '.$waypointIndex),
                    'latitude' => $isFirst || $isLast ? $start['latitude'] : $point['latitude'],
                    'longitude' => $isFirst || $isLast ? $start['longitude'] : $point['longitude'],
                ];
            })
            ->all());
    }

    private function backtrackMeters(array $points): float
    {
        if (count($points) < 4) {
            return 0.0;
        }

        $segments = [];

        for ($index = 1; $index < count($points); $index++) {
            $from = $points[$index - 1];
            $to = $points[$index];
            $length = $this->distanceBetween($from, $to);

            if ($length < 15) {
                continue;
            }

            $segments[] = [
                'index' => $index - 1,
                'from' => $from,
                'to' => $to,
                'midpoint' => [
                    'latitude' => (((float) $from['latitude']) + ((float) $to['latitude'])) / 2,
                    'longitude' => (((float) $from['longitude']) + ((float) $to['longitude'])) / 2,
                ],
                'bearing' => $this->bearingBetween($from, $to),
                'length' => $length,
            ];
        }

        if (count($segments) < 3) {
            return 0.0;
        }

        $marked = [];
        $segmentCount = count($segments);

        for ($left = 0; $left < $segmentCount; $left++) {
            for ($right = $left + 2; $right < $segmentCount; $right++) {
                if ($this->distanceBetween($segments[$left]['midpoint'], $segments[$right]['midpoint']) > 75) {
                    continue;
                }

                if ($this->bearingDifference($segments[$left]['bearing'], $segments[$right]['bearing']) < 145) {
                    continue;
                }

                $marked[$left] = true;
                $marked[$right] = true;
                break;
            }
        }

        $meters = 0.0;

        foreach (array_keys($marked) as $segmentIndex) {
            $meters += $segments[$segmentIndex]['length'];
        }

        return $meters;
    }

    private function distanceBetween(array $from, array $to): float
    {
        $earthRadius = 6371000;
        $lat1 = deg2rad((float) $from['latitude']);
        $lat2 = deg2rad((float) $to['latitude']);
        $deltaLatitude = deg2rad((float) $to['latitude'] - (float) $from['latitude']);
        $deltaLongitude = deg2rad((float) $to['longitude'] - (float) $from['longitude']);
        $a = sin($deltaLatitude / 2) ** 2
            + cos($lat1) * cos($lat2) * sin($deltaLongitude / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function bearingBetween(array $from, array $to): float
    {
        $lat1 = deg2rad((float) $from['latitude']);
        $lat2 = deg2rad((float) $to['latitude']);
        $deltaLongitude = deg2rad((float) $to['longitude'] - (float) $from['longitude']);
        $y = sin($deltaLongitude) * cos($lat2);
        $x = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($deltaLongitude);

        return fmod(rad2deg(atan2($y, $x)) + 360, 360);
    }

    private function bearingDifference(float $first, float $second): float
    {
        $difference = abs($first - $second);

        return $difference > 180 ? 360 - $difference : $difference;
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
                'continue_straight' => 'true',
            ]);
        $this->recordRoutingUsage('osrm', $profile, $response->successful() ? 'ok' : 'failed', [
            'http_status' => $response->status(),
            'waypoints' => count($points),
        ]);

        if (! $response->successful()) {
            return null;
        }

        $route = data_get($response->json(), 'routes.0');

        return is_array($route) ? $route : null;
    }

    private function fetchMapboxRoute(array $points, string $profile): ?array
    {
        $token = (string) config('sport_map.routing.mapbox_access_token', '');

        if ($token === '') {
            $this->recordRoutingUsage('mapbox', $profile, 'missing_token', [
                'waypoints' => count($points),
            ]);

            return null;
        }

        $coordinates = collect($points)
            ->map(fn (array $point) => (float) $point['longitude'].','.(float) $point['latitude'])
            ->implode(';');
        $baseUrl = rtrim((string) config('sport_map.routing.mapbox_base_url', 'https://api.mapbox.com'), '/');

        $response = Http::timeout(max(1, (int) config('sport_map.routing.timeout_seconds', 4)))
            ->acceptJson()
            ->get("{$baseUrl}/directions/v5/mapbox/{$profile}/{$coordinates}", [
                'access_token' => $token,
                'overview' => 'full',
                'geometries' => 'geojson',
                'steps' => 'true',
                'continue_straight' => 'true',
            ]);
        $this->recordRoutingUsage('mapbox', $profile, $response->successful() ? 'ok' : 'failed', [
            'http_status' => $response->status(),
            'waypoints' => count($points),
        ]);

        if (! $response->successful()) {
            return null;
        }

        $route = data_get($response->json(), 'routes.0');

        return is_array($route) ? $route : null;
    }

    private function fetchGraphHopperRoute(array $points, string $profile): ?array
    {
        $apiKey = (string) config('sport_map.routing.graphhopper_api_key', '');

        if ($apiKey === '') {
            $this->recordRoutingUsage('graphhopper', $profile, 'missing_token', [
                'waypoints' => count($points),
            ]);

            return null;
        }

        $query = [
            'profile' => $profile,
            'locale' => 'de',
            'points_encoded' => 'false',
            'instructions' => 'true',
            'calc_points' => 'true',
            'key' => $apiKey,
        ];

        $pointQuery = collect($points)
            ->map(fn (array $point) => 'point='.rawurlencode((float) $point['latitude'].','.(float) $point['longitude']))
            ->implode('&');
        $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $baseUrl = rtrim((string) config('sport_map.routing.graphhopper_base_url', 'https://graphhopper.com/api/1'), '/');

        $response = Http::timeout(max(1, (int) config('sport_map.routing.timeout_seconds', 4)))
            ->acceptJson()
            ->get("{$baseUrl}/route?{$pointQuery}&{$queryString}");
        $this->recordRoutingUsage('graphhopper', $profile, $response->successful() ? 'ok' : 'failed', [
            'http_status' => $response->status(),
            'waypoints' => count($points),
        ]);

        if (! $response->successful()) {
            return null;
        }

        $path = data_get($response->json(), 'paths.0');

        if (! is_array($path)) {
            return null;
        }

        return [
            'distance' => data_get($path, 'distance'),
            'duration' => ((float) data_get($path, 'time', 0)) / 1000,
            'geometry' => [
                'type' => 'LineString',
                'coordinates' => data_get($path, 'points.coordinates', []),
            ],
            'legs' => [[
                'steps' => collect((array) data_get($path, 'instructions', []))
                    ->map(fn (array $instruction) => [
                        'distance' => data_get($instruction, 'distance'),
                        'duration' => ((float) data_get($instruction, 'time', 0)) / 1000,
                        'name' => data_get($instruction, 'street_name'),
                        'maneuver' => [
                            'type' => $this->graphHopperSignToManeuver((int) data_get($instruction, 'sign', 0)),
                        ],
                    ])
                    ->values()
                    ->all(),
            ]],
        ];
    }

    private function fetchGraphHopperRoundTripRoute(array $start, string $profile, int $targetDistanceMeters, int $seed, float $heading): ?array
    {
        $apiKey = (string) config('sport_map.routing.graphhopper_api_key', '');

        if ($apiKey === '') {
            $this->recordRoutingUsage('graphhopper', $profile, 'missing_token', [
                'waypoints' => 1,
                'algorithm' => 'round_trip',
            ]);

            return null;
        }

        $query = [
            'point' => (float) $start['latitude'].','.(float) $start['longitude'],
            'profile' => $profile,
            'locale' => 'de',
            'points_encoded' => 'false',
            'instructions' => 'true',
            'calc_points' => 'true',
            'algorithm' => 'round_trip',
            'round_trip.distance' => max(1000, $targetDistanceMeters),
            'round_trip.seed' => $seed,
            'heading' => (int) round($heading),
            'key' => $apiKey,
        ];
        $baseUrl = rtrim((string) config('sport_map.routing.graphhopper_base_url', 'https://graphhopper.com/api/1'), '/');

        $response = Http::timeout(max(1, (int) config('sport_map.routing.timeout_seconds', 4)))
            ->acceptJson()
            ->get("{$baseUrl}/route", $query);
        $this->recordRoutingUsage('graphhopper', $profile, $response->successful() ? 'ok' : 'failed', [
            'http_status' => $response->status(),
            'waypoints' => 1,
            'algorithm' => 'round_trip',
        ]);

        if (! $response->successful()) {
            return null;
        }

        $path = data_get($response->json(), 'paths.0');

        if (! is_array($path)) {
            return null;
        }

        return [
            'distance' => data_get($path, 'distance'),
            'duration' => ((float) data_get($path, 'time', 0)) / 1000,
            'geometry' => [
                'type' => 'LineString',
                'coordinates' => data_get($path, 'points.coordinates', []),
            ],
            'legs' => [[
                'steps' => collect((array) data_get($path, 'instructions', []))
                    ->map(fn (array $instruction) => [
                        'distance' => data_get($instruction, 'distance'),
                        'duration' => ((float) data_get($instruction, 'time', 0)) / 1000,
                        'name' => data_get($instruction, 'street_name'),
                        'maneuver' => [
                            'type' => $this->graphHopperSignToManeuver((int) data_get($instruction, 'sign', 0)),
                        ],
                    ])
                    ->values()
                    ->all(),
            ]],
        ];
    }

    private function summaryFromExternalRoute(array $route, array $fallback, string $provider, string $profile): array
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
            return $this->externalRoutingFallback($fallback, $provider, $profile, 'invalid_geometry');
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
            'calculation' => $provider.'_route_v1',
            'routing_provider' => $provider,
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

    private function graphHopperSignToManeuver(int $sign): string
    {
        return match ($sign) {
            4 => 'arrive',
            5 => 'via',
            -3, -2, -1 => 'turn',
            1, 2, 3 => 'turn',
            default => 'continue',
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

    private function externalRoutingFallback(array $fallback, string $provider, string $profile, string $reason): array
    {
        return array_replace($fallback, [
            'routing_provider' => $provider,
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

    private function profileFor(string $provider, ?string $sportType): string
    {
        $profiles = match ($provider) {
            'mapbox' => config('sport_map.routing.mapbox_profiles', []),
            'graphhopper' => config('sport_map.routing.graphhopper_profiles', []),
            default => config('sport_map.routing.profiles', []),
        };
        $key = strtolower((string) $sportType);

        return (string) ($profiles[$key] ?? $profiles['other'] ?? ($provider === 'mapbox' ? 'walking' : 'foot'));
    }

    private function recordRoutingUsage(string $provider, string $profile, string $status, array $metadata = []): void
    {
        $this->usage->record(
            'routing',
            $provider,
            'directions',
            'route',
            auth()->user(),
            1,
            status: $status,
            metadata: [
                'profile' => $profile,
                ...$metadata,
            ],
        );
    }
}
