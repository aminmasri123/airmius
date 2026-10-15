<?php

return [
    'disk' => env('BACKUP_DISK', env('APP_ENV') === 'production' ? 'r2_backup' : 'local'),
    'path' => trim(env('BACKUP_PATH', 'backups/database'), '/'),
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),
    'rpo_hours' => (int) env('BACKUP_RPO_HOURS', 24),
    'rto_hours' => (int) env('BACKUP_RTO_HOURS', 4),
    'responsible_role' => env('BACKUP_RESPONSIBLE_ROLE', 'DevOps / SRE'),
    'encryption_at_rest_required' => (bool) env('BACKUP_ENCRYPTION_AT_REST_REQUIRED', true),
    'encryption_enabled' => (bool) env('BACKUP_ENCRYPTION_ENABLED', env('APP_ENV') === 'production'),
    'encryption_key' => env('BACKUP_ENCRYPTION_KEY'),
    'restore_drill_required' => (bool) env('BACKUP_RESTORE_DRILL_REQUIRED', true),
    'mysqldump_binary' => env('MYSQLDUMP_BINARY', 'mysqldump'),
    'mysql_binary' => env('MYSQL_BINARY', 'mysql'),
    'process_timeout' => (int) env('BACKUP_PROCESS_TIMEOUT', 900),
    'deletion_ledger_enabled' => (bool) env('DELETION_RECOVERY_LEDGER_ENABLED', env('APP_ENV') !== 'testing'),
    'deletion_ledger_disk' => env('DELETION_RECOVERY_LEDGER_DISK', env('BACKUP_DISK', 'local')),
    'deletion_ledger_path' => trim(env('DELETION_RECOVERY_LEDGER_PATH', 'backups/privacy-deletions'), '/'),
    'deletion_ledger_signing_key' => env('DELETION_RECOVERY_LEDGER_SIGNING_KEY', env('APP_KEY')),
];
