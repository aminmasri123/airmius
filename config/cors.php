<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://airmius.com',
        'https://www.airmius.com',
        'https://app.airmius.com',
        'http://localhost',
        'http://127.0.0.1',
    ],

    'allowed_origins_patterns' => [
        '#^http://localhost:[0-9]+$#',
        '#^http://127\.0\.0\.1:[0-9]+$#',
        '#^https?://0\.0\.0\.0(:[0-9]+)?$#',
        '#^https?://10\.[0-9]+\.[0-9]+\.[0-9]+(:[0-9]+)?$#',
        '#^https?://172\.(1[6-9]|2[0-9]|3[0-1])\.[0-9]+\.[0-9]+(:[0-9]+)?$#',
        '#^https?://192\.168\.[0-9]+\.[0-9]+(:[0-9]+)?$#',
        '#^https?://[^/]+\.local(:[0-9]+)?$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Authorization', 'Content-Type'],

    'max_age' => 86400,

    'supports_credentials' => false,
];
