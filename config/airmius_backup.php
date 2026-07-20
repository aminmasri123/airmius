<?php

return [
    'disk' => env('BACKUP_DISK', env('FILESYSTEM_DISK', 'local')),
    'path' => trim(env('BACKUP_PATH', 'backups/database'), '/'),
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),
];
