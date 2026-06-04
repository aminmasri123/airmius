<?php

namespace App\Support;

use App\Models\SportRoute;
use App\Models\SportRouteTrack;
use Illuminate\Support\Str;

class SportMapAppExportManifest
{
    public const VERSION = '2026-06-03';

    public static function forRoute(SportRoute $route): array
    {
        $metrics = $route->metrics ?? [];
        $intelligence = data_get($metrics, 'route_intelligence', []);
        $points = self::routePoints($route);
        $distanceMeters = (float) ($route->distance_meters ?? data_get($metrics, 'distance_meters', 0));
        $warnings = data_get($intelligence, 'safety_warnings', []);

        return self::manifest(
            type: 'route',
            id: (int) $route->id,
            title: $route->title ?: 'Airmius Route',
            updatedAt: $route->updated_at?->timestamp,
            distanceMeters: $distanceMeters,
            points: $points,
            intelligence: $intelligence,
            warnings: is_array($warnings) ? $warnings : [],
            gpxUrl: route('api.v1.sport-routes.gpx', $route->id),
            webGpxUrl: route('auth.sport-routes.gpx', $route->id),
            syncPrefix: 'sport_route'
        );
    }

    public static function forTrack(SportRouteTrack $track): array
    {
        $metrics = $track->metrics ?? [];
        $intelligence = data_get($metrics, 'route_intelligence', []);
        $points = self::trackPoints($track);
        $distanceMeters = (float) ($track->distance_meters ?? data_get($metrics, 'distance_meters', 0));
        $warnings = data_get($intelligence, 'safety_warnings', []);

        return self::manifest(
            type: 'track',
            id: (int) $track->id,
            title: $track->title ?: 'Airmius Track',
            updatedAt: $track->updated_at?->timestamp,
            distanceMeters: $distanceMeters,
            points: $points,
            intelligence: $intelligence,
            warnings: is_array($warnings) ? $warnings : [],
            gpxUrl: route('api.v1.sport-tracks.gpx', $track->id),
            webGpxUrl: route('auth.sport-tracks.gpx', $track->id),
            syncPrefix: 'sport_track'
        );
    }

    protected static function manifest(
        string $type,
        int $id,
        string $title,
        ?int $updatedAt,
        float $distanceMeters,
        array $points,
        array $intelligence,
        array $warnings,
        string $gpxUrl,
        string $webGpxUrl,
        string $syncPrefix
    ): array {
        $geometryHash = sha1(json_encode($points, JSON_UNESCAPED_SLASHES) ?: "{$type}:{$id}");
        $navigationReadiness = data_get($intelligence, 'navigation_readiness', []);
        $navigationManifest = data_get($intelligence, 'navigation_manifest', []);
        $offlinePack = data_get($intelligence, 'offline_pack', []);
        $hasTurnByTurn = data_get($navigationReadiness, 'turn_by_turn_ready', false)
            || data_get($navigationReadiness, 'has_navigation_cues', false)
            || count($points) >= 2;

        return [
            'version' => self::VERSION,
            'type' => $type,
            'entity_id' => $id,
            'sync_key' => "{$syncPrefix}:{$id}:".($updatedAt ?? 0),
            'geometry_hash' => $geometryHash,
            'gpx' => [
                'available' => true,
                'url' => $gpxUrl,
                'web_url' => $webGpxUrl,
                'filename' => self::filename($title, $type),
                'mime_type' => 'application/gpx+xml',
            ],
            'offline' => [
                'available' => (bool) data_get($offlinePack, 'available', count($points) >= 2),
                'pack_id' => "{$type}-{$id}-".substr($geometryHash, 0, 12),
                'estimated_size_kb' => self::estimatedOfflineSize($distanceMeters, count($points)),
                'estimated_tiles' => (int) data_get($offlinePack, 'estimated_tiles', max(1, (int) ceil($distanceMeters / 1200))),
                'zoom_levels' => data_get($offlinePack, 'zoom_levels', [13, 14, 15]),
                'tile_bounds' => data_get($offlinePack, 'tile_bounds', []),
                'required_permissions' => ['location_when_in_use', 'offline_storage'],
                'cache_strategy' => 'download_before_start',
            ],
            'navigation' => [
                'mode' => $hasTurnByTurn ? 'turn_by_turn_or_follow_line' : 'follow_line_preview',
                'turn_by_turn_ready' => (bool) $hasTurnByTurn,
                'voice_guidance_ready' => (bool) $hasTurnByTurn,
                'voice_guidance' => data_get($navigationManifest, 'voice_guidance', []),
                'off_route_detection' => data_get($navigationManifest, 'off_route_detection', []),
                'maneuver_count' => (int) data_get($navigationManifest, 'maneuver_count', 0),
                'readiness_level' => data_get($navigationReadiness, 'level', 'draft'),
                'distance_meters' => round($distanceMeters, 1),
                'point_count' => count($points),
                'fallback_mode' => 'follow_line',
            ],
            'safety' => [
                'warning_count' => count($warnings),
                'warnings' => $warnings,
                'live_tracking_recommended' => count($warnings) > 0 || $distanceMeters >= 10000,
                'pre_start_checklist' => [
                    'download_offline_pack',
                    'share_route_if_remote',
                    'check_weather_and_surface',
                ],
            ],
        ];
    }

    protected static function routePoints(SportRoute $route): array
    {
        $coordinates = data_get($route->route_geometry ?? [], 'coordinates', []);

        if (is_array($coordinates) && $coordinates !== []) {
            return array_values($coordinates);
        }

        $waypoints = $route->waypoints ?? [];

        if (is_array($waypoints) && $waypoints !== []) {
            return array_values(array_map(fn (array $point) => [
                (float) ($point['longitude'] ?? 0),
                (float) ($point['latitude'] ?? 0),
            ], $waypoints));
        }

        return [];
    }

    protected static function trackPoints(SportRouteTrack $track): array
    {
        $coordinates = data_get($track->track_geometry ?? [], 'coordinates', []);

        if (is_array($coordinates) && $coordinates !== []) {
            return array_values($coordinates);
        }

        $points = $track->track_points ?? [];

        if (is_array($points) && $points !== []) {
            return array_values(array_map(fn (array $point) => [
                (float) ($point['longitude'] ?? 0),
                (float) ($point['latitude'] ?? 0),
            ], $points));
        }

        return [];
    }

    protected static function estimatedOfflineSize(float $distanceMeters, int $pointCount): int
    {
        return max(96, (int) ceil(($distanceMeters / 1000) * 88 + $pointCount * 4));
    }

    protected static function filename(string $title, string $type): string
    {
        $slug = Str::slug($title) ?: "airmius-{$type}";

        return "{$slug}.gpx";
    }
}
