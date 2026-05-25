<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Airmius AI gateway
    |--------------------------------------------------------------------------
    |
    | The app talks to this gateway instead of binding features directly to one
    | vendor. That keeps nutrition, training and blog generation switchable
    | between Google, OpenAI and EU providers without rewriting screens.
    |
    */

    'enabled' => (bool) env('AIRMIUS_AI_ENABLED', true),
    'primary_provider' => env('AIRMIUS_AI_PRIMARY_PROVIDER', 'ionos'),
    'fallback_provider' => env('AIRMIUS_AI_FALLBACK_PROVIDER', 'openai'),
    'timeout' => (int) env('AIRMIUS_AI_TIMEOUT', 20),

    'privacy' => [
        'strip_exif' => true,
        'store_uploads' => (bool) env('AIRMIUS_AI_STORE_UPLOADS', false),
        'max_image_kb' => (int) env('AIRMIUS_AI_MAX_IMAGE_KB', 5120),
        'max_image_dimension' => (int) env('AIRMIUS_AI_IMAGE_MAX_DIMENSION', 1024),
        'jpeg_quality' => (int) env('AIRMIUS_AI_IMAGE_JPEG_QUALITY', 82),
    ],

    'features' => [
        'nutrition_image_analysis' => [
            'enabled' => (bool) env('AIRMIUS_AI_NUTRITION_IMAGE_ENABLED', true),
            'primary_provider' => env('AIRMIUS_AI_NUTRITION_IMAGE_PROVIDER', env('AIRMIUS_AI_PRIMARY_PROVIDER', 'ionos')),
            'fallback_provider' => env('AIRMIUS_AI_NUTRITION_IMAGE_FALLBACK_PROVIDER', env('AIRMIUS_AI_FALLBACK_PROVIDER', 'openai')),
        ],
        'training_plan_generation' => [
            'enabled' => (bool) env('AIRMIUS_AI_TRAINING_PLAN_ENABLED', true),
            'primary_provider' => env('AIRMIUS_AI_TRAINING_PLAN_PROVIDER', env('AIRMIUS_AI_PRIMARY_PROVIDER', 'ionos')),
            'fallback_provider' => env('AIRMIUS_AI_TRAINING_PLAN_FALLBACK_PROVIDER', env('AIRMIUS_AI_FALLBACK_PROVIDER', 'openai')),
            'max_items' => (int) env('AIRMIUS_AI_TRAINING_PLAN_MAX_ITEMS', 156),
            'output_tokens' => (int) env('AIRMIUS_AI_TRAINING_PLAN_OUTPUT_TOKENS', 9000),
        ],
    ],

    'providers' => [
        'google' => [
            'label' => 'Google Gemini',
            'api_key' => env('GOOGLE_GEMINI_API_KEY'),
            'base_url' => env('GOOGLE_GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com'),
            'model' => env('GOOGLE_GEMINI_MODEL', 'gemini-3.1-flash-lite'),
        ],
        'openai' => [
            'label' => 'OpenAI',
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'model' => env('OPENAI_MODEL', 'gpt-5.4-mini'),
        ],
        'ionos' => [
            'label' => 'IONOS AI Model Hub',
            'api_key' => env('IONOS_AI_API_KEY'),
            'base_url' => env('IONOS_AI_BASE_URL', 'https://openai.inference.de-txl.ionos.com/v1'),
            'model' => env('IONOS_AI_MODEL', 'mistralai/Mistral-Small-24B-Instruct'),
            'api_style' => 'openai_chat_completions',
        ],
    ],
];
