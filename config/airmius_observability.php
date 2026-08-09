<?php

return [
    'minimum_evidence_window_hours' => (int) env('OBSERVABILITY_MINIMUM_EVIDENCE_WINDOW_HOURS', 24),
    'evidence_path' => env(
        'OBSERVABILITY_EVIDENCE_PATH',
        base_path('resources/release/observability_evidence.local.json'),
    ),
    'platform_gate_path' => env(
        'OBSERVABILITY_PLATFORM_GATE_PATH',
        base_path('resources/release/platform_release_gates.json'),
    ),
];
