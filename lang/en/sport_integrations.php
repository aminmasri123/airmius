<?php

return [
    'providers' => [
        'apple_health' => [
            'label' => 'Apple Health',
            'description' => 'Apple Health can import normalized workout and route data through HealthKit and permissions you explicitly grant.',
        ],
        'google_fit' => [
            'label' => 'Google Fit',
            'description' => 'Connect through Google OAuth. Activities can be synchronized with the stored tokens.',
        ],
        'garmin' => [
            'label' => 'Garmin',
            'description' => 'The Garmin Health API requires provider approval. You can register your interest until partner access is enabled.',
        ],
        'strava' => [
            'label' => 'Strava',
            'description' => 'Strava connects running, cycling, swimming and workout data through the official OAuth API.',
        ],
        'fitbit' => [
            'label' => 'Fitbit',
            'description' => 'The Fitbit Web API can provide activities, steps, distance, calories and health data through OAuth. The integration is planned.',
        ],
        'polar' => [
            'label' => 'Polar',
            'description' => 'Polar AccessLink provides training and activity data through OAuth/API. The integration is planned.',
        ],
        'mi_fitness' => [
            'label' => 'Mi Fitness',
            'description' => 'Mi Fitness can connect through Android Health Connect or a normalized file import. Airmius only receives activity fields you explicitly allow.',
        ],
    ],
    'summary' => [
        'requested' => 'Connection requested. We will notify you when this provider becomes available.',
        'native_ready' => 'The secure normalized import is ready. Start the import in the Airmius mobile app.',
        'connected' => 'Account connected. You can synchronize now.',
        'normalized_import_ready' => 'Normalized activities can be imported securely.',
        'activity_imported' => 'Activity imported.',
    ],
    'flash' => [
        'requested' => ':provider was requested.',
        'native_ready' => ':provider is ready for secure import through the Airmius mobile app.',
        'not_configured' => ':provider is not configured yet. Add the client ID and client secret to the environment.',
        'expired' => 'The :provider connection has expired. Select Connect again.',
        'token_exchange_failed' => ':provider could not exchange the authorization code for tokens: :error',
        'connected' => ':provider was connected.',
        'activity_created' => 'Training session added.',
        'disconnected' => 'Sport app connection removed.',
        'activity_deleted' => 'Imported activity deleted.',
        'activity_renamed' => 'Imported activity renamed.',
        'activities_deleted' => ':count imported activities were deleted.',
    ],
    'errors' => [
        'unknown_provider' => 'Unknown provider error',
        'route_unavailable' => 'The selected route is not available to your account.',
        'team_unavailable' => 'The selected team does not belong to your account.',
        'route_team_mismatch' => 'The route and team do not belong to the same training context.',
    ],
];
