<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class SportRouteMetricService
{
    private const EARTH_RADIUS_METERS = 6371000;

    private const SPEEDS_MPS = [
        'walking' => 1.35,
        'wandern' => 1.25,
        'running' => 2.8,
        'laufen' => 2.8,
        'trail_running' => 2.35,
        'cycling' => 5.8,
        'radfahren' => 5.8,
        'mountainbike' => 4.2,
        'skateboard' => 3.4,
        'skating' => 3.6,
        'football' => 2.2,
        'soccer' => 2.2,
        'default' => 2.4,
    ];

    public function normalizePoints(array $points): array
    {
        return collect($points)
            ->values()
            ->map(function (array $point, int $index) {
                return array_filter([
                    'name' => isset($point['name']) ? trim((string) $point['name']) : null,
                    'latitude' => round((float) $point['latitude'], 7),
                    'longitude' => round((float) $point['longitude'], 7),
                    'elevation_m' => isset($point['elevation_m']) && $point['elevation_m'] !== '' ? round((float) $point['elevation_m'], 1) : null,
                    'recorded_at' => $point['recorded_at'] ?? null,
                    'accuracy_m' => isset($point['accuracy_m']) && $point['accuracy_m'] !== '' ? round((float) $point['accuracy_m'], 1) : null,
                    'order' => $index + 1,
                ], fn ($value) => $value !== null && $value !== '');
            })
            ->all();
    }

    public function summarize(array $points, ?string $sportType = null, ?CarbonInterface $startedAt = null, ?CarbonInterface $endedAt = null): array
    {
        $distanceMeters = $this->distanceMeters($points);
        [$gainMeters, $lossMeters] = $this->elevationDelta($points);
        $durationSeconds = $this->durationSeconds($points, $startedAt, $endedAt)
            ?? $this->estimatedDurationSeconds($distanceMeters, $sportType);
        $maxSpeed = $this->maxSegmentSpeed($points);

        return [
            'distance_meters' => $distanceMeters,
            'duration_seconds' => $durationSeconds,
            'estimated_duration_seconds' => $this->estimatedDurationSeconds($distanceMeters, $sportType),
            'elevation_gain_meters' => $gainMeters,
            'elevation_loss_meters' => $lossMeters,
            'average_speed_mps' => $durationSeconds > 0 ? round($distanceMeters / $durationSeconds, 3) : null,
            'max_speed_mps' => $maxSpeed,
            'bounds' => $this->bounds($points),
            'geometry' => $this->lineString($points),
            'navigation_cues' => $this->navigationCues($points),
        ];
    }

    public function distanceMeters(array $points): int
    {
        $distance = 0.0;

        for ($index = 1; $index < count($points); $index++) {
            $distance += $this->haversineMeters($points[$index - 1], $points[$index]);
        }

        return (int) round($distance);
    }

    public function nearbyDistanceMeters(float $latitude, float $longitude, float $targetLatitude, float $targetLongitude): int
    {
        return (int) round($this->haversineMeters(
            ['latitude' => $latitude, 'longitude' => $longitude],
            ['latitude' => $targetLatitude, 'longitude' => $targetLongitude],
        ));
    }

    private function estimatedDurationSeconds(int $distanceMeters, ?string $sportType): int
    {
        $key = strtolower((string) $sportType);
        $speed = self::SPEEDS_MPS[$key] ?? self::SPEEDS_MPS['default'];

        return $distanceMeters > 0 ? (int) max(60, round($distanceMeters / $speed)) : 0;
    }

    private function durationSeconds(array $points, ?CarbonInterface $startedAt, ?CarbonInterface $endedAt): ?int
    {
        if ($startedAt && $endedAt && $endedAt->greaterThan($startedAt)) {
            return $startedAt->diffInSeconds($endedAt);
        }

        $first = $points[0]['recorded_at'] ?? null;
        $last = $points[count($points) - 1]['recorded_at'] ?? null;

        if (! $first || ! $last) {
            return null;
        }

        $from = Carbon::parse($first);
        $to = Carbon::parse($last);

        return $to->greaterThan($from) ? $from->diffInSeconds($to) : null;
    }

    private function maxSegmentSpeed(array $points): ?float
    {
        $maxSpeed = null;

        for ($index = 1; $index < count($points); $index++) {
            $fromTime = $points[$index - 1]['recorded_at'] ?? null;
            $toTime = $points[$index]['recorded_at'] ?? null;

            if (! $fromTime || ! $toTime) {
                continue;
            }

            $seconds = Carbon::parse($fromTime)->diffInSeconds(Carbon::parse($toTime));

            if ($seconds <= 0) {
                continue;
            }

            $speed = $this->haversineMeters($points[$index - 1], $points[$index]) / $seconds;
            $maxSpeed = $maxSpeed === null ? $speed : max($maxSpeed, $speed);
        }

        return $maxSpeed !== null ? round($maxSpeed, 3) : null;
    }

    private function elevationDelta(array $points): array
    {
        $gain = 0.0;
        $loss = 0.0;

        for ($index = 1; $index < count($points); $index++) {
            if (! isset($points[$index - 1]['elevation_m'], $points[$index]['elevation_m'])) {
                continue;
            }

            $delta = (float) $points[$index]['elevation_m'] - (float) $points[$index - 1]['elevation_m'];

            if ($delta > 0) {
                $gain += $delta;
            } else {
                $loss += abs($delta);
            }
        }

        return [(int) round($gain), (int) round($loss)];
    }

    private function bounds(array $points): ?array
    {
        if ($points === []) {
            return null;
        }

        $latitudes = array_column($points, 'latitude');
        $longitudes = array_column($points, 'longitude');

        return [
            'north' => max($latitudes),
            'south' => min($latitudes),
            'east' => max($longitudes),
            'west' => min($longitudes),
        ];
    }

    private function lineString(array $points): array
    {
        return [
            'type' => 'LineString',
            'coordinates' => collect($points)
                ->map(fn (array $point) => array_values(array_filter([
                    (float) $point['longitude'],
                    (float) $point['latitude'],
                    $point['elevation_m'] ?? null,
                ], fn ($value) => $value !== null)))
                ->all(),
        ];
    }

    private function navigationCues(array $points): array
    {
        $cues = [];

        for ($index = 1; $index < count($points); $index++) {
            $from = $points[$index - 1];
            $to = $points[$index];
            $distance = (int) round($this->haversineMeters($from, $to));

            $cues[] = [
                'type' => $index === 1 ? 'start' : 'continue',
                'from' => $from['name'] ?? 'Punkt '.$index,
                'to' => $to['name'] ?? 'Punkt '.($index + 1),
                'distance_meters' => $distance,
                'bearing_degrees' => $this->bearingDegrees($from, $to),
            ];
        }

        if ($points !== []) {
            $last = $points[count($points) - 1];
            $cues[] = [
                'type' => 'finish',
                'to' => $last['name'] ?? 'Ziel',
                'distance_meters' => 0,
            ];
        }

        return $cues;
    }

    private function haversineMeters(array $from, array $to): float
    {
        $lat1 = deg2rad((float) $from['latitude']);
        $lat2 = deg2rad((float) $to['latitude']);
        $deltaLat = deg2rad((float) $to['latitude'] - (float) $from['latitude']);
        $deltaLon = deg2rad((float) $to['longitude'] - (float) $from['longitude']);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1) * cos($lat2) * sin($deltaLon / 2) ** 2;

        return self::EARTH_RADIUS_METERS * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function bearingDegrees(array $from, array $to): int
    {
        $lat1 = deg2rad((float) $from['latitude']);
        $lat2 = deg2rad((float) $to['latitude']);
        $deltaLon = deg2rad((float) $to['longitude'] - (float) $from['longitude']);
        $y = sin($deltaLon) * cos($lat2);
        $x = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($deltaLon);

        return (int) round(fmod(rad2deg(atan2($y, $x)) + 360, 360));
    }
}
