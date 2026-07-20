<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CreateDatabaseBackup extends Command
{
    protected $signature = 'airmius:backup-database
        {--disk= : Filesystem disk for the backup}
        {--path= : Backup path inside the selected disk}';

    protected $description = 'Create a database backup with a JSON manifest.';

    public function handle(): int
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver !== 'sqlite') {
            $this->components->error("Database backup currently supports sqlite only; current driver is {$driver}.");

            return self::FAILURE;
        }

        $database = (string) config("database.connections.{$connection}.database");

        if ($database === '' || $database === ':memory:' || ! is_file($database)) {
            $this->components->error('SQLite database file was not found and cannot be backed up.');

            return self::FAILURE;
        }

        $disk = (string) ($this->option('disk') ?: config('airmius_backup.disk', 'local'));
        $path = (string) ($this->option('path') ?: $this->defaultPath($connection));

        $stream = fopen($database, 'rb');

        if ($stream === false) {
            $this->components->error('SQLite database file could not be opened for reading.');

            return self::FAILURE;
        }

        try {
            Storage::disk($disk)->put($path, $stream);
        } finally {
            fclose($stream);
        }

        $manifest = [
            'schema' => 'airmius.database-backup.v1',
            'created_at' => now()->toJSON(),
            'app' => [
                'name' => config('app.name'),
                'environment' => app()->environment(),
            ],
            'database' => [
                'connection' => $connection,
                'driver' => $driver,
                'source_file' => basename($database),
            ],
            'backup' => [
                'disk' => $disk,
                'path' => $path,
                'size_bytes' => filesize($database) ?: 0,
                'sha256' => hash_file('sha256', $database),
            ],
            'retention_days' => (int) config('airmius_backup.retention_days', 30),
        ];

        Storage::disk($disk)->put(
            $path.'.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );

        $this->components->info("Database backup created at {$disk}:{$path}");
        $this->line("Manifest: {$disk}:{$path}.json");

        return self::SUCCESS;
    }

    private function defaultPath(string $connection): string
    {
        $basePath = trim((string) config('airmius_backup.path', 'backups/database'), '/');
        $timestamp = now()->format('Ymd-His');

        return "{$basePath}/airmius-{$connection}-{$timestamp}.sqlite";
    }
}
