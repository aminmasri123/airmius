<?php

return [
    'disk' => env('BACKUP_DISK', env('FILESYSTEM_DISK', 'local')),
    'path' => trim(env('BACKUP_PATH', 'backups/database'), '/'),
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),
    'rpo_hours' => (int) env('BACKUP_RPO_HOURS', 24),
    'rto_hours' => (int) env('BACKUP_RTO_HOURS', 4),
    'responsible_role' => env('BACKUP_RESPONSIBLE_ROLE', 'DevOps / SRE'),
    'encryption_at_rest_required' => (bool) env('BACKUP_ENCRYPTION_AT_REST_REQUIRED', true),
    'restore_drill_required' => (bool) env('BACKUP_RESTORE_DRILL_REQUIRED', true),
    'mysqldump_binary' => env('MYSQLDUMP_BINARY', 'mysqldump'),
    'mysql_binary' => env('MYSQL_BINARY', 'mysql'),
    'process_timeout' => (int) env('BACKUP_PROCESS_TIMEOUT', 900),
];
