<?php

$stage = static function (string $name, int $default = 100): int {
    $value = trim((string) env($name, $default));

    return preg_match('/^(0|5|25|100)$/', $value) === 1 ? (int) $value : -1;
};
$killSwitch = static fn (string $name): bool => (bool) env($name, false);
$configuredSalt = trim((string) env('AIRMIUS_ROLLOUT_SALT', ''));

return [
    /*
    | Rollouts are deliberately stateless. The salt is never exposed in a
    | response or audit report and defaults to the application key.
    */
    'salt' => $configuredSalt !== '' ? $configuredSalt : (string) env('APP_KEY', ''),
    'global_kill_switch' => (bool) env('AIRMIUS_ROLLOUT_KILL_SWITCH', false),
    'pilot_override' => (bool) env('AIRMIUS_ROLLOUT_PILOT_OVERRIDE', true),
    'retry_after_seconds' => max(60, (int) env('AIRMIUS_ROLLOUT_RETRY_AFTER_SECONDS', 300)),
    'web_fallback_path' => '/workspaces',

    'features' => [
        'club_operating_system' => [
            'stage' => $stage('AIRMIUS_ROLLOUT_CLUB_OPERATING_SYSTEM'),
            'kill_switch' => $killSwitch('AIRMIUS_ROLLOUT_KILL_CLUB_OPERATING_SYSTEM'),
        ],
        'coach_daily_control' => [
            'stage' => $stage('AIRMIUS_ROLLOUT_COACH_DAILY_CONTROL'),
            'kill_switch' => $killSwitch('AIRMIUS_ROLLOUT_KILL_COACH_DAILY_CONTROL'),
        ],
        'growth_workspaces' => [
            'stage' => $stage('AIRMIUS_ROLLOUT_GROWTH_WORKSPACES'),
            'kill_switch' => $killSwitch('AIRMIUS_ROLLOUT_KILL_GROWTH_WORKSPACES'),
        ],
        'admin_operations' => [
            'stage' => $stage('AIRMIUS_ROLLOUT_ADMIN_OPERATIONS'),
            'kill_switch' => $killSwitch('AIRMIUS_ROLLOUT_KILL_ADMIN_OPERATIONS'),
        ],
    ],
];
