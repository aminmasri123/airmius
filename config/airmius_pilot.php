<?php

$clubIds = array_values(array_unique(array_filter(array_map(
    static fn (string $value): int => max(0, (int) trim($value)),
    explode(',', (string) env('PILOT_CLUB_IDS', '')),
))));

return [
    'enabled' => env('PILOT_ENABLED', false),
    'club_ids' => $clubIds,
    'minimum_clubs' => 3,
    'maximum_clubs' => 5,
    'duration_weeks' => min(max((int) env('PILOT_DURATION_WEEKS', 8), 6), 8),
    'baseline_days' => min(max((int) env('PILOT_BASELINE_DAYS', 28), 14), 90),
    'minimum_onboarding_percent' => min(max((int) env('PILOT_MIN_ONBOARDING_PERCENT', 80), 50), 100),
    'evidence_path' => env(
        'PILOT_EVIDENCE_PATH',
        resource_path('release/club_pilot_evidence.local.json'),
    ),
];
