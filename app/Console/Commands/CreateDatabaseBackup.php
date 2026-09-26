<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class CreateDatabaseBackup extends Command
{
    protected $signature = 'airmius:backup-database
        {--disk= : Filesystem disk for the backup}
        {--path= : Backup path inside the selected disk}';

    protected $description = 'Create a consistent SQLite or MySQL database backup with a verified JSON manifest.';

    public function handle(): int
    {
        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");

        if (! in_array($driver, ['sqlite', 'mysql'], true)) {
            $this->components->error("Database backup does not support driver {$driver}.");
            return self::FAILURE;
        }

        $disk = (string) ($this->option('disk') ?: config('airmius_backup.disk', 'local'));
        $path = (string) ($this->option('path') ?: $this->defaultPath($connection, $driver));
        $temporary = tempnam(sys_get_temp_dir(), 'airmius-db-');

        if ($temporary === false) {
            $this->components->error('A temporary backup file could not be created.');
            return self::FAILURE;
        }

        try {
            $source = $driver === 'sqlite'
                ? $this->copySqlite($connection, $temporary)
                : $this->dumpMysql($connection, $temporary);

            $stream = fopen($temporary, 'rb');
            if ($stream === false) {
                throw new RuntimeException('Temporary backup could not be opened.');
            }
            try {
                Storage::disk($disk)->put($path, $stream);
            } finally {
                fclose($stream);
            }

            $manifest = [
                'schema' => 'airmius.database-backup.v2',
                'created_at' => now()->toJSON(),
                'app' => ['name' => config('app.name'), 'environment' => app()->environment()],
                'database' => ['connection' => $connection, 'driver' => $driver, 'source' => $source],
                'backup' => [
                    'disk' => $disk,
                    'path' => $path,
                    'size_bytes' => filesize($temporary) ?: 0,
                    'sha256' => hash_file('sha256', $temporary),
                ],
                'retention_days' => (int) config('airmius_backup.retention_days', 30),
                'operations' => [
                    'rpo_hours' => (int) config('airmius_backup.rpo_hours', 24),
                    'rto_hours' => (int) config('airmius_backup.rto_hours', 4),
                    'responsible_role' => (string) config('airmius_backup.responsible_role', 'DevOps / SRE'),
                    'encryption_at_rest_required' => (bool) config('airmius_backup.encryption_at_rest_required', true),
                    'restore_drill_required' => (bool) config('airmius_backup.restore_drill_required', true),
                ],
            ];
            Storage::disk($disk)->put($path.'.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
        } catch (\Throwable $error) {
            $this->components->error($error->getMessage());
            return self::FAILURE;
        } finally {
            @unlink($temporary);
        }

        $this->components->info("Database backup created at {$disk}:{$path}");
        $this->line("Manifest: {$disk}:{$path}.json");
        return self::SUCCESS;
    }

    protected function dumpMysql(string $connection, string $target): string
    {
        $config = config("database.connections.{$connection}");
        $database = (string) ($config['database'] ?? '');
        if ($database === '') {
            throw new RuntimeException('MySQL database name is missing.');
        }

        $defaults = $this->mysqlDefaultsFile((array) $config);
        try {
            $arguments = [
                (string) config('airmius_backup.mysqldump_binary', 'mysqldump'),
                "--defaults-extra-file={$defaults}",
                '--single-transaction', '--quick', '--routines', '--triggers', '--events',
                '--hex-blob', '--set-gtid-purged=OFF', '--no-tablespaces',
                "--result-file={$target}", $database,
            ];
            $process = new Process($arguments);
            $process->setTimeout((int) config('airmius_backup.process_timeout', 900));
            $process->mustRun();
        } finally {
            @unlink($defaults);
        }

        if (! is_file($target) || filesize($target) === 0) {
            throw new RuntimeException('mysqldump produced an empty backup.');
        }

        return $database;
    }

    protected function copySqlite(string $connection, string $target): string
    {
        $database = (string) config("database.connections.{$connection}.database");
        if ($database === '' || $database === ':memory:' || ! is_file($database) || ! copy($database, $target)) {
            throw new RuntimeException('SQLite database file was not found or could not be copied.');
        }
        return basename($database);
    }

    protected function mysqlDefaultsFile(array $config): string
    {
        $path = sys_get_temp_dir().'/airmius-mysql-'.Str::uuid().'.cnf';
        $lines = ['[client]'];
        foreach (['host', 'port', 'username', 'password', 'unix_socket'] as $key) {
            $value = $config[$key] ?? null;
            if ($value !== null && $value !== '') {
                $name = $key === 'username' ? 'user' : $key;
                $lines[] = $name.'="'.addcslashes((string) $value, "\\\"").'"';
            }
        }
        file_put_contents($path, implode(PHP_EOL, $lines).PHP_EOL, LOCK_EX);
        chmod($path, 0600);
        return $path;
    }

    private function defaultPath(string $connection, string $driver): string
    {
        $base = trim((string) config('airmius_backup.path', 'backups/database'), '/');
        $extension = $driver === 'mysql' ? 'sql' : 'sqlite';
        return "{$base}/airmius-{$connection}-".now()->format('Ymd-His').".{$extension}";
    }
}
