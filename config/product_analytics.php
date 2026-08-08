<?php

return [
    // Privacy by default: enable only after the processing purpose and
    // versioned consent copy have been approved for the target environment.
    'enabled' => env('PRODUCT_ANALYTICS_ENABLED', false),
    'consent_version' => env('PRODUCT_ANALYTICS_CONSENT_VERSION', 'product-analytics-v1'),
    'minimum_group_size' => max(5, (int) env('PRODUCT_ANALYTICS_MIN_GROUP_SIZE', 5)),
    'allowed_windows' => [7, 28, 90],
];
