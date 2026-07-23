<?php

return [
    'disk' => env('BACKUP_DISK', env('FILESYSTEM_DISK', 'local')),
    'path' => trim(env('BACKUP_PATH', 'backups/database'), '/'),
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),
    'mysqldump_binary' => env('MYSQLDUMP_BINARY', 'mysqldump'),
    'mysql_binary' => env('MYSQL_BINARY', 'mysql'),
    'process_timeout' => (int) env('BACKUP_PROCESS_TIMEOUT', 900),
];
