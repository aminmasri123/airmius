<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class RestoreDatabaseBackup extends Command
{
    protected $signature = 'airmius:restore-database
        {backup : Backup path inside the selected disk, or an absolute local path}
        {--disk= : Filesystem disk containing the backup}
        {--target= : Absolute path of the SQLite file to write}
        {--target-database= : Existing non-production MySQL database to restore into}
        {--connection= : MySQL connection whose server credentials are used}
        {--force : Overwrite the target file when it already exists}';

    protected $description = 'Restore a SQLite or MySQL backup to a separate target and verify its manifest hash.';

    public function handle(): int
    {
        $backup = (string) $this->argument('backup');
        $target = (string) $this->option('target');
        $targetDatabase = (string) $this->option('target-database');

        if ($targetDatabase !== '') {
            $disk = (string) ($this->option('disk') ?: config('airmius_backup.disk', 'local'));
            return $this->restoreMysql($backup, $targetDatabase, $this->manifestFor($backup, $disk));
        }

        if ($target === '') {
            $this->components->error('Use --target=/absolute/path/to/restore.sqlite to avoid accidental live restores.');

            return self::FAILURE;
        }

        if (! Str::startsWith($target, DIRECTORY_SEPARATOR)) {
            $this->components->error('The restore target must be an absolute path.');

            return self::FAILURE;
        }

        if (File::exists($target) && ! $this->option('force')) {
            $this->components->error('The restore target already exists. Re-run with --force if this is intentional.');

            return self::FAILURE;
        }

        $disk = (string) ($this->option('disk') ?: config('airmius_backup.disk', 'local'));
        $sourceStream = $this->openBackupStream($backup, $disk);

        if ($sourceStream === false) {
            $this->components->error('Backup file was not found or could not be opened.');

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($target));

        $temporaryTarget = $target.'.tmp-'.Str::uuid();
        $targetStream = fopen($temporaryTarget, 'wb');

        if ($targetStream === false) {
            fclose($sourceStream);
            $this->components->error('Restore target could not be opened for writing.');

            return self::FAILURE;
        }

        try {
            stream_copy_to_stream($sourceStream, $targetStream);
        } finally {
            fclose($sourceStream);
            fclose($targetStream);
        }

        $manifest = $this->manifestFor($backup, $disk);
        $expectedHash = $manifest['backup']['sha256'] ?? null;

        if ($expectedHash && ! hash_equals((string) $expectedHash, hash_file('sha256', $temporaryTarget))) {
            File::delete($temporaryTarget);
            $this->components->error('Restore hash verification failed.');

            return self::FAILURE;
        }

        File::move($temporaryTarget, $target);

        $this->components->info("Database backup restored to {$target}");

        if ($expectedHash) {
            $this->line('Manifest hash verified.');
        }

        return self::SUCCESS;
    }

    private function restoreMysql(string $backup, string $targetDatabase, ?array $manifest): int
    {
        if ($targetDatabase === '') {
            $this->components->error('Use --target-database=<separate_restore_database> for a MySQL restore.');
            return self::FAILURE;
        }

        $connection = (string) ($this->option('connection') ?: config('database.default'));
        $config = (array) config("database.connections.{$connection}");
        if (($config['driver'] ?? null) !== 'mysql') {
            $this->components->error("Connection {$connection} is not a MySQL connection.");
            return self::FAILURE;
        }
        if (hash_equals((string) ($config['database'] ?? ''), $targetDatabase)) {
            $this->components->error('Refusing to restore into the currently configured application database.');
            return self::FAILURE;
        }
        if (! $this->option('force')) {
            $this->components->error('MySQL restore requires --force after the separate target database has been verified.');
            return self::FAILURE;
        }

        $disk = (string) ($this->option('disk') ?: config('airmius_backup.disk', 'local'));
        $source = $this->openBackupStream($backup, $disk);
        if ($source === false) {
            $this->components->error('Backup file was not found or could not be opened.');
            return self::FAILURE;
        }
        $temporary = tempnam(sys_get_temp_dir(), 'airmius-restore-');
        if ($temporary === false) {
            fclose($source);
            $this->components->error('Temporary restore file could not be created.');
            return self::FAILURE;
        }
        $target = fopen($temporary, 'wb');
        if ($target === false) {
            if (is_resource($source)) fclose($source);
            $this->components->error('Temporary restore file could not be created.');
            return self::FAILURE;
        }
        try {
            stream_copy_to_stream($source, $target);
        } finally {
            fclose($source);
            fclose($target);
        }

        try {
            $expected = $manifest['backup']['sha256'] ?? null;
            if ($expected && ! hash_equals((string) $expected, hash_file('sha256', $temporary))) {
                $this->components->error('Restore hash verification failed.');
                return self::FAILURE;
            }
            $defaults = $this->mysqlDefaultsFile($config);
            $input = fopen($temporary, 'rb');
            try {
                $process = new Process([(string) config('airmius_backup.mysql_binary', 'mysql'), "--defaults-extra-file={$defaults}", $targetDatabase]);
                $process->setInput($input);
                $process->setTimeout((int) config('airmius_backup.process_timeout', 900));
                $process->mustRun();
            } finally {
                if (is_resource($input)) fclose($input);
                @unlink($defaults);
            }
        } catch (\Throwable $error) {
            $this->components->error($error->getMessage());
            return self::FAILURE;
        } finally {
            @unlink($temporary);
        }

        $this->components->info("MySQL backup restored into separate database {$targetDatabase}.");
        $this->line('Manifest hash verified. Run application integrity checks before any recovery cutover.');
        return self::SUCCESS;
    }

    private function mysqlDefaultsFile(array $config): string
    {
        $path = sys_get_temp_dir().'/airmius-mysql-'.Str::uuid().'.cnf';
        $lines = ['[client]'];
        foreach (['host', 'port', 'username', 'password', 'unix_socket'] as $key) {
            $value = $config[$key] ?? null;
            if ($value !== null && $value !== '') {
                $lines[] = ($key === 'username' ? 'user' : $key).'="'.addcslashes((string) $value, "\\\"").'"';
            }
        }
        file_put_contents($path, implode(PHP_EOL, $lines).PHP_EOL, LOCK_EX);
        chmod($path, 0600);
        return $path;
    }

    private function openBackupStream(string $backup, string $disk)
    {
        if (Str::startsWith($backup, DIRECTORY_SEPARATOR)) {
            return is_file($backup) ? fopen($backup, 'rb') : false;
        }

        return Storage::disk($disk)->exists($backup)
            ? Storage::disk($disk)->readStream($backup)
            : false;
    }

    private function manifestFor(string $backup, string $disk): ?array
    {
        $manifestPath = $backup.'.json';

        if (Str::startsWith($backup, DIRECTORY_SEPARATOR)) {
            if (! is_file($manifestPath)) {
                return null;
            }

            $contents = File::get($manifestPath);
        } elseif (Storage::disk($disk)->exists($manifestPath)) {
            $contents = Storage::disk($disk)->get($manifestPath);
        } else {
            return null;
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : null;
    }
}
