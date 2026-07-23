<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'google_fit' => [
        'client_id' => env('GOOGLE_FIT_CLIENT_ID'),
        'client_secret' => env('GOOGLE_FIT_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_FIT_REDIRECT_URI'),
    ],

    'strava' => [
        'client_id' => env('STRAVA_CLIENT_ID'),
        'client_secret' => env('STRAVA_CLIENT_SECRET'),
        'redirect' => env('STRAVA_REDIRECT_URI'),
    ],

    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => env('MICROSOFT_REDIRECT_URI'),
        'tenant' => env('MICROSOFT_TENANT_ID', 'common'),
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'webhook_tolerance' => (int) env('STRIPE_WEBHOOK_TOLERANCE_SECONDS', 300),
    ],

    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        'commerce_webhook_id' => env('PAYPAL_COMMERCE_WEBHOOK_ID', env('PAYPAL_WEBHOOK_ID')),
        'outfit_webhook_id' => env('PAYPAL_OUTFIT_WEBHOOK_ID', env('PAYPAL_WEBHOOK_ID')),
        'mode' => env('PAYPAL_MODE', 'sandbox'),
    ],

    'mobile_push' => [
        'fcm' => [
            'credentials' => env('FIREBASE_CREDENTIALS'),
            'project_id' => env('FIREBASE_PROJECT_ID'),
        ],
        'expo' => [
            'access_token' => env('EXPO_ACCESS_TOKEN'),
        ],
        'max_attempts' => (int) env('MOBILE_PUSH_MAX_ATTEMPTS', 5),
        'retry_base_seconds' => (int) env('MOBILE_PUSH_RETRY_BASE_SECONDS', 60),
    ],

    'geoip' => [
        'url' => env('GEOIP_API_URL'),
    ],

];
