<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneDatabaseBackups extends Command
{
    protected $signature = 'airmius:prune-database-backups
        {--disk= : Filesystem disk containing the backups}
        {--path= : Backup directory inside the selected disk}
        {--days= : Maximum retention in days}
        {--dry-run : List expired objects without deleting them}';

    protected $description = 'Delete database backup objects older than the configured maximum retention.';

    public function handle(): int
    {
        $diskName = (string) ($this->option('disk') ?: config('airmius_backup.disk', 'local'));
        $path = trim((string) ($this->option('path') ?: config('airmius_backup.path', 'backups/database')), '/');
        $days = (int) ($this->option('days') ?: config('airmius_backup.retention_days', 30));
        if ($days < 1) {
            $this->components->error('Retention must be at least one day.');

            return self::FAILURE;
        }

        $disk = Storage::disk($diskName);
        $cutoff = now()->subDays($days)->getTimestamp();
        $expired = [];
        foreach ($disk->allFiles($path) as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                $expired[] = $file;
            }
        }

        if ($this->option('dry-run')) {
            foreach ($expired as $file) {
                $this->line($file);
            }
            $this->components->info(count($expired).' expired backup objects found; nothing deleted.');

            return self::SUCCESS;
        }

        if ($expired !== []) {
            $disk->delete($expired);
        }
        $this->components->info(count($expired).' expired backup objects deleted.');

        return self::SUCCESS;
    }
}
