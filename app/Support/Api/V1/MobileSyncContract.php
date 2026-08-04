<?php

namespace App\Support\Api\V1;

class MobileSyncContract
{
    public const CONTRACT_VERSION = '2026-06-03';

    public static function domains(): array
    {
        return [
            self::domain('profile', '/api/v1/me', 'network_first', 86400, false),
            self::domain('daily_flow', '/api/v1/dashboard/daily-flow', 'network_first', 900, false),
            self::domain('notifications', '/api/v1/notifications', 'network_first', 300, true),
            self::domain('feed', '/api/v1/feed', 'network_first', 600, true),
            self::domain('teams', '/api/v1/teams', 'stale_while_revalidate', 1800, true),
            self::domain('events', '/api/v1/events', 'network_first', 900, true),
            self::domain('training_plans', '/api/v1/training/plans', 'stale_while_revalidate', 3600, true),
            self::domain('training_logs', '/api/v1/training/logs', 'network_first', 1800, true),
            self::domain('nutrition', '/api/v1/nutrition', 'network_first', 900, true),
            self::domain('sport_routes', '/api/v1/sport-routes', 'stale_while_revalidate', 86400, true, ['gpx_export', 'offline_pack']),
            self::domain('sport_tracks', '/api/v1/sport-tracks', 'network_first', 86400, true, ['gpx_export', 'background_tracking']),
            self::domain('sport_places', '/api/v1/sport-places', 'stale_while_revalidate', 86400, true),
            self::domain('commerce_products', '/api/v1/commerce/products', 'stale_while_revalidate', 1800, true),
            self::domain('commerce_orders', '/api/v1/commerce/orders', 'network_first', 600, true),
            self::domain('uploads', '/api/v1/uploads', 'network_only', 0, false, ['camera', 'gallery']),
        ];
    }

    public static function retryPolicy(): array
    {
        return [
            'idempotency_header' => 'Idempotency-Key',
            'request_id_header' => 'X-Request-Id',
            'retryable_statuses' => [408, 409, 425, 429, 500, 502, 503, 504],
            'non_retryable_statuses' => [400, 401, 403, 404, 422],
            'backoff' => [
                'strategy' => 'exponential_jitter',
                'initial_ms' => 750,
                'max_ms' => 30000,
                'max_attempts' => 5,
            ],
            'safe_methods' => ['GET', 'HEAD', 'OPTIONS'],
            'idempotent_write_methods' => ['POST', 'PATCH', 'DELETE'],
            'offline_queue' => [
                'enabled' => true,
                'persist_until' => 'acknowledged_or_user_discards',
                'conflict_resolution' => 'server_wins_with_client_retry_prompt',
            ],
        ];
    }

    public static function permissions(): array
    {
        return [
            [
                'key' => 'notifications',
                'platforms' => ['ios', 'android'],
                'required_for' => ['event_reminders', 'chat_mentions', 'training_plan_updates', 'commerce_order_updates'],
                'fallback' => 'in_app_notifications',
            ],
            [
                'key' => 'location_foreground',
                'platforms' => ['ios', 'android'],
                'required_for' => ['nearby_sport_places', 'route_planning', 'map_centering'],
                'fallback' => 'manual_location_search',
            ],
            [
                'key' => 'location_background',
                'platforms' => ['ios', 'android'],
                'required_for' => ['background_track_recording', 'live_tracking'],
                'fallback' => 'foreground_tracking_only',
            ],
            [
                'key' => 'camera',
                'platforms' => ['ios', 'android'],
                'required_for' => ['meal_image_analysis', 'story_upload', 'profile_photo_upload'],
                'fallback' => 'gallery_upload',
            ],
            [
                'key' => 'photo_library',
                'platforms' => ['ios', 'android'],
                'required_for' => ['uploads', 'story_upload', 'meal_image_analysis'],
                'fallback' => 'camera_capture',
            ],
        ];
    }

    public static function pushChannels(): array
    {
        return [
            [
                'key' => 'event_reminders',
                'importance' => 'high',
                'deep_link' => 'airmius://events/{event}',
                'fallback_url' => '/events/{event}',
            ],
            [
                'key' => 'chat_mentions',
                'importance' => 'high',
                'deep_link' => 'airmius://chat/{conversation}',
                'fallback_url' => '/chat?conversation={conversation}',
            ],
            [
                'key' => 'social_updates',
                'importance' => 'high',
                'deep_link' => 'airmius://friends',
                'fallback_url' => '/friends',
            ],
            [
                'key' => 'training_updates',
                'importance' => 'default',
                'deep_link' => 'airmius://training/plans/{trainingPlan}',
                'fallback_url' => '/training',
            ],
            [
                'key' => 'commerce_orders',
                'importance' => 'default',
                'deep_link' => 'airmius://commerce/orders/{order}',
                'fallback_url' => '/commerce?order={order}',
            ],
            [
                'key' => 'club_billing',
                'importance' => 'default',
                'deep_link' => 'airmius://clubs/{club}/billing',
                'fallback_url' => '/club-cockpit',
            ],
        ];
    }

    public static function deepLinks(): array
    {
        return [
            'scheme' => 'airmius',
            'resolver_endpoint' => '/api/v1/mobile/deep-links/resolve',
            'universal_link_hosts' => ['airmius.de', 'www.airmius.de'],
            'routes' => [
                'airmius://dashboard',
                'airmius://profile/{user}',
                'airmius://clubs/{club}',
                'airmius://clubs/{club}/billing',
                'airmius://clubs/{club}/membership-requests',
                'airmius://teams/{team}',
                'airmius://feed/{post}',
                'airmius://posts/{post}',
                'airmius://chat/{conversation}',
                'airmius://conversations/{conversation}',
                'airmius://events/{event}',
                'airmius://invitations/{token}',
                'airmius://team-invitations/token/{token}/accept',
                'airmius://club-member-invitations/token/{token}/accept',
                'airmius://training/plans/{trainingPlan}',
                'airmius://nutrition',
                'airmius://sport-routes/{sportRoute}',
                'airmius://sport-tracks/{sportTrack}',
                'airmius://commerce/products/{product}',
                'airmius://commerce/orders/{order}',
            ],
        ];
    }

    public static function designSystem(): array
    {
        return DesignSystemContract::summary();
    }

    private static function domain(
        string $key,
        string $endpoint,
        string $cacheStrategy,
        int $ttlSeconds,
        bool $supportsDelta,
        array $features = [],
    ): array {
        return [
            'key' => $key,
            'endpoint' => $endpoint,
            'cache_strategy' => $cacheStrategy,
            'ttl_seconds' => $ttlSeconds,
            'supports_delta' => $supportsDelta,
            'delta_query' => $supportsDelta ? 'updated_since' : null,
            'features' => $features,
        ];
    }
}
