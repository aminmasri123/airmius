<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RestoreDatabaseBackup extends Command
{
    protected $signature = 'airmius:restore-database
        {backup : Backup path inside the selected disk, or an absolute local path}
        {--disk= : Filesystem disk containing the backup}
        {--target= : Absolute path of the SQLite file to write}
        {--force : Overwrite the target file when it already exists}';

    protected $description = 'Restore a SQLite database backup into a target file and verify its manifest hash when available.';

    public function handle(): int
    {
        $backup = (string) $this->argument('backup');
        $target = (string) $this->option('target');

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
