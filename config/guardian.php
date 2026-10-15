<?php

return [
    'consent_version' => env('GUARDIAN_CONSENT_VERSION', 'guardian-consent-v1-2026-08-22'),
    'consent_token_ttl_minutes' => (int) env('GUARDIAN_CONSENT_TOKEN_TTL_MINUTES', 1440),
];
