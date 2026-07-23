<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AIRMIUS Operations Monitoring
    |--------------------------------------------------------------------------
    |
    | These checks are intentionally small and runnable from cron, scheduler,
    | CI, or a host-level monitor. They cover the MVP operational risks before
    | external tools such as Sentry, Horizon, or uptime probes are connected.
    |
    */

    'window_hours' => (int) env('OPERATIONS_MONITOR_WINDOW_HOURS', 24),

    'errors' => [
        'max_recent_errors' => (int) env('OPERATIONS_MAX_RECENT_ERRORS', 0),
        'scan_bytes' => (int) env('OPERATIONS_LOG_SCAN_BYTES', 262144),
        'log_file_patterns' => [
            storage_path('logs/laravel.log'),
            storage_path('logs/laravel-*.log'),
        ],
        'require_external_monitoring_in_production' => env('OPERATIONS_REQUIRE_EXTERNAL_ERROR_MONITORING', true),
        'external_dsn' => env('ERROR_MONITORING_DSN'),
    ],

    'queue' => [
        'required_tables' => ['jobs', 'failed_jobs', 'job_batches'],
        'max_failed_jobs' => (int) env('OPERATIONS_MAX_FAILED_JOBS', 0),
        'max_stale_jobs' => (int) env('OPERATIONS_MAX_STALE_JOBS', 0),
        'stale_after_minutes' => (int) env('OPERATIONS_STALE_JOB_MINUTES', 15),
        'warn_sync_queue_in_production' => env('OPERATIONS_WARN_SYNC_QUEUE_IN_PRODUCTION', true),
    ],

    'jobs' => [
        'required_commands' => [
            'airmius:send-membership-billing-reminders',
            'airmius:generate-recurring-contribution-invoices',
            'airmius:send-subscription-invoice-emails',
            'airmius:process-subscription-lifecycle',
            'airmius:send-event-reminders',
            'airmius:send-learning-drip-notifications',
            'airmius:monitor-learning-health',
            'airmius:check-ai-provider-tokens',
            'airmius:process-inactive-accounts',
            'airmius:prune-ad-events',
            'airmius:prune-expired-stories',
            'airmius:mobile-push-dispatch',
        ],
    ],

    'webhooks' => [
        'required_routes' => [
            'webhooks.stripe',
            'webhooks.paypal',
            'webhooks.commerce.stripe',
            'webhooks.commerce.paypal',
            'webhooks.outfit-subscriptions.paypal',
        ],
        'warn_missing_provider_secrets' => env('OPERATIONS_WARN_MISSING_WEBHOOK_SECRETS', true),
    ],

    'mail' => [
        'max_failed_deliveries' => (int) env('OPERATIONS_MAX_FAILED_MAIL_DELIVERIES', 0),
        'fail_log_mailer_in_production' => env('OPERATIONS_FAIL_LOG_MAILER_IN_PRODUCTION', true),
    ],

    'backup' => [
        'enabled' => env('OPERATIONS_MONITOR_BACKUPS', false),
        'max_age_hours' => (int) env('OPERATIONS_BACKUP_MAX_AGE_HOURS', 30),
        'fail_local_disk_in_production' => env('OPERATIONS_FAIL_LOCAL_BACKUP_IN_PRODUCTION', true),
    ],

    'mobile_push' => [
        'enabled' => env('OPERATIONS_MONITOR_MOBILE_PUSH', false),
        'stale_queued_minutes' => (int) env('OPERATIONS_PUSH_STALE_MINUTES', 30),
        'max_recent_failures' => (int) env('OPERATIONS_MAX_FAILED_PUSH', 0),
        'require_firebase' => env('OPERATIONS_REQUIRE_FIREBASE', true),
    ],
];
