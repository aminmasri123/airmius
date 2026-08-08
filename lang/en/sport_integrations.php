<?php

return [
    'providers' => [
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
            'description' => 'Mi Fitness does not provide a simple standard OAuth connection. Your interest in this connection will be recorded.',
        ],
    ],
    'summary' => [
        'requested' => 'Connection requested. We will notify you when this provider becomes available.',
        'connected' => 'Account connected. You can synchronize now.',
    ],
    'flash' => [
        'requested' => ':provider was requested.',
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
    ],
];
