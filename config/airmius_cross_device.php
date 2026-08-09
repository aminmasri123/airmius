<?php

return [
    'evidence_path' => env(
        'CROSS_DEVICE_EVIDENCE_PATH',
        base_path('resources/release/cross_device_evidence.local.json'),
    ),
    'mobile_manifest_path' => env(
        'CROSS_DEVICE_MOBILE_MANIFEST_PATH',
        base_path('mobile/airmius_mobile/store_listing/release/release_evidence_manifest.json'),
    ),
    'platform_gate_path' => env(
        'CROSS_DEVICE_PLATFORM_GATE_PATH',
        base_path('resources/release/platform_release_gates.json'),
    ),
];
