<?php

namespace App\Support;

use Illuminate\Support\Arr;

final class SportIntegrationProviderRegistry
{
    public const NORMALIZED_IMPORT_PROVIDERS = [
        'apple_health',
        'google_fit',
        'garmin',
        'strava',
        'mi_fitness',
    ];

    private const DEFINITIONS = [
        'apple_health' => [
            'surfaces' => ['mobile'],
            'label_key' => 'sport_integrations.providers.apple_health.label',
            'description_key' => 'sport_integrations.providers.apple_health.description',
            'mobile_status' => 'native_bridge',
            'connection_mode' => 'ios_healthkit_normalized_import',
            'direction' => ['import'],
            'supports_gps_samples' => true,
            'supports_background_sync' => true,
            'supports_direct_sync' => false,
            'import_scopes' => ['workouts', 'workout_routes', 'heart_rate', 'active_energy'],
            'next_action' => 'request_ios_healthkit_permissions',
            'request_message_key' => 'fitness.providerAppleHealth',
        ],
        'google_fit' => [
            'surfaces' => ['web', 'mobile'],
            'label_key' => 'sport_integrations.providers.google_fit.label',
            'description_key' => 'sport_integrations.providers.google_fit.description',
            'route_key' => 'google-fit',
            'web_status' => 'live_oauth',
            'mobile_status' => 'live_oauth',
            'connection_mode' => 'oauth_or_android_health_connect_normalized_import',
            'direction' => ['import'],
            'supports_gps_samples' => true,
            'supports_background_sync' => true,
            'supports_direct_sync' => true,
            'web_scopes' => [
                'openid',
                'profile',
                'email',
                'https://www.googleapis.com/auth/fitness.activity.read',
                'https://www.googleapis.com/auth/fitness.location.read',
            ],
            'import_scopes' => ['activity', 'location', 'calories'],
            'next_action' => 'oauth_or_android_health_permissions',
            'request_message_key' => 'fitness.providerGoogleFit',
        ],
        'garmin' => [
            'surfaces' => ['web', 'mobile'],
            'label_key' => 'sport_integrations.providers.garmin.label',
            'description_key' => 'sport_integrations.providers.garmin.description',
            'web_status' => 'partner_required',
            'mobile_status' => 'partner_required',
            'connection_mode' => 'garmin_health_api_or_file_bridge',
            'direction' => ['import'],
            'supports_gps_samples' => true,
            'supports_background_sync' => false,
            'supports_direct_sync' => false,
            'web_scopes' => ['activities', 'wellness'],
            'import_scopes' => ['activities', 'wellness'],
            'next_action' => 'collect_partner_interest_or_import_file',
            'request_message_key' => 'fitness.providerGarmin',
        ],
        'strava' => [
            'surfaces' => ['web', 'mobile'],
            'label_key' => 'sport_integrations.providers.strava.label',
            'description_key' => 'sport_integrations.providers.strava.description',
            'web_status' => 'live_oauth',
            'mobile_status' => 'live_oauth',
            'connection_mode' => 'oauth_import_gpx_export',
            'direction' => ['import', 'export_gpx'],
            'supports_gps_samples' => true,
            'supports_background_sync' => true,
            'supports_direct_sync' => true,
            'web_scopes' => ['read', 'activity:read_all'],
            'import_scopes' => ['read', 'activity:read_all'],
            'next_action' => 'oauth_connect_or_upload_gpx',
            'request_message_key' => 'fitness.providerStrava',
        ],
        'fitbit' => [
            'surfaces' => ['web'],
            'label_key' => 'sport_integrations.providers.fitbit.label',
            'description_key' => 'sport_integrations.providers.fitbit.description',
            'web_status' => 'planned_oauth',
            'supports_direct_sync' => false,
            'web_scopes' => ['activity', 'profile'],
        ],
        'polar' => [
            'surfaces' => ['web'],
            'label_key' => 'sport_integrations.providers.polar.label',
            'description_key' => 'sport_integrations.providers.polar.description',
            'web_status' => 'planned_oauth',
            'supports_direct_sync' => false,
            'web_scopes' => ['accesslink.read_all'],
        ],
        'mi_fitness' => [
            'surfaces' => ['web', 'mobile'],
            'label_key' => 'sport_integrations.providers.mi_fitness.label',
            'description_key' => 'sport_integrations.providers.mi_fitness.description',
            'web_status' => 'native_bridge',
            'mobile_status' => 'native_bridge',
            'connection_mode' => 'android_health_connect_or_file_normalized_import',
            'direction' => ['import'],
            'supports_gps_samples' => true,
            'supports_background_sync' => false,
            'supports_direct_sync' => false,
            'web_scopes' => ['activities'],
            'import_scopes' => ['activities', 'routes', 'heart_rate', 'energy_burned'],
            'next_action' => 'request_android_health_connect_permissions_or_import_file',
            'request_message_key' => 'fitness.providerMiFitness',
        ],
    ];

    public static function webDefinitions(): array
    {
        return collect(self::DEFINITIONS)
            ->filter(fn (array $definition) => in_array('web', $definition['surfaces'], true))
            ->map(fn (array $definition) => [
                'label_key' => $definition['label_key'],
                'route_key' => $definition['route_key'] ?? null,
                'status' => $definition['web_status'],
                'description_key' => $definition['description_key'],
                'scopes' => $definition['web_scopes'] ?? [],
                'supports_direct_sync' => (bool) ($definition['supports_direct_sync'] ?? false),
                'connection_mode' => $definition['connection_mode'] ?? null,
            ])
            ->all();
    }

    public static function localizedWebDefinitions(): array
    {
        return collect(self::webDefinitions())
            ->map(fn (array $definition) => [
                ...$definition,
                'label' => __($definition['label_key']),
                'description' => __($definition['description_key']),
            ])
            ->all();
    }

    public static function mobileDefinitions(): array
    {
        return collect(self::DEFINITIONS)
            ->filter(fn (array $definition) => in_array('mobile', $definition['surfaces'], true))
            ->map(fn (array $definition, string $key) => [
                'key' => $key,
                'label' => __($definition['label_key']),
                'status' => $definition['mobile_status'],
                'connection_mode' => $definition['connection_mode'],
                'direction' => $definition['direction'],
                'supports_gps_samples' => (bool) $definition['supports_gps_samples'],
                'supports_background_sync' => (bool) $definition['supports_background_sync'],
                'supports_direct_sync' => (bool) $definition['supports_direct_sync'],
                'scopes' => $definition['import_scopes'],
                'next_action' => $definition['next_action'],
                'request_message' => __($definition['description_key']),
                'request_message_key' => $definition['request_message_key'],
            ])
            ->values()
            ->all();
    }

    public static function definition(string $provider): ?array
    {
        return self::DEFINITIONS[$provider] ?? null;
    }

    public static function label(string $provider): string
    {
        $key = self::DEFINITIONS[$provider]['label_key'] ?? null;

        return $key ? __($key) : str($provider)->headline()->toString();
    }

    public static function defaultImportScopes(string $provider): array
    {
        return Arr::get(self::DEFINITIONS, "{$provider}.import_scopes", []);
    }
}
