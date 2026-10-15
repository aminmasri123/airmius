<?php

namespace App\Console\Commands;

use App\Models\File;
use App\Support\UploadStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MigratePrivateUploads extends Command
{
    protected $signature = 'airmius:migrate-private-uploads
        {--execute : Copy protected files, update their paths, and remove public source objects}
        {--keep-source : Keep the public source objects after a verified migration}
        {--limit=0 : Process at most this many file records}';

    protected $description = 'Move protected File records from the public upload disk to private storage.';

    private int $selected = 0;

    private int $migrated = 0;

    private int $cleaned = 0;

    private int $skippedPublic = 0;

    private int $failed = 0;

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $keepSource = (bool) $this->option('keep-source');
        $limit = max(0, (int) $this->option('limit'));

        $publicDiskName = (string) config('filesystems.uploads_disk', 'public');
        $privateDiskName = UploadStorage::privateDisk();

        if ($execute && $publicDiskName === $privateDiskName) {
            $this->components->error('Public and private upload disks must be different before migration.');

            return self::FAILURE;
        }

        File::query()
            ->with(['posts:id,visibility', 'messages:id'])
            ->orderBy('id')
            ->chunkById(100, function ($files) use ($execute, $keepSource, $limit, $publicDiskName, $privateDiskName) {
                foreach ($files as $file) {
                    if ($limit > 0 && $this->selected >= $limit) {
                        return false;
                    }

                    if ($this->isPublicPostOnly($file)) {
                        $this->skippedPublic++;

                        continue;
                    }

                    if (str_starts_with((string) $file->path, 'private-post-media/')) {
                        continue;
                    }

                    $this->selected++;

                    if (! $execute) {
                        continue;
                    }

                    try {
                        if (UploadStorage::isPrivate($file->path)) {
                            $this->cleanupOriginals($file, $publicDiskName, $keepSource);

                            continue;
                        }

                        $oldPath = $file->path;
                        $oldThumbnailPath = $file->thumbnail_path;
                        $newPath = $this->copyVerified($oldPath, $publicDiskName, $privateDiskName);
                        $newThumbnailPath = $this->copyVerified($oldThumbnailPath, $publicDiskName, $privateDiskName);

                        $file->forceFill([
                            'path' => $newPath,
                            'thumbnail_path' => $newThumbnailPath,
                        ])->saveOrFail();

                        if (! $keepSource) {
                            $this->deleteVerified($oldPath, $publicDiskName);
                            $this->deleteVerified($oldThumbnailPath, $publicDiskName);
                        }

                        $this->migrated++;
                    } catch (\Throwable $error) {
                        $this->failed++;
                        report($error);
                        $this->components->error("File record {$file->id} failed: {$error->getMessage()}");
                    }
                }

                return true;
            });

        $mode = $execute ? 'EXECUTE' : 'DRY RUN';
        $this->table(['Mode', 'Selected', 'Migrated', 'Cleaned', 'Public post media skipped', 'Failed'], [[
            $mode,
            $this->selected,
            $this->migrated,
            $this->cleaned,
            $this->skippedPublic,
            $this->failed,
        ]]);

        if (! $execute) {
            $this->components->warn('No files were changed. Run again with --execute after configuring and testing the private disk.');
        }

        return $this->failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function isPublicPostOnly(File $file): bool
    {
        return $file->posts->isNotEmpty()
            && $file->messages->isEmpty()
            && $file->posts->every(fn ($post) => $post->visibility === 'public');
    }

    private function copyVerified(?string $sourcePath, string $sourceDiskName, string $targetDiskName): ?string
    {
        if (! $sourcePath) {
            return null;
        }

        $source = Storage::disk($sourceDiskName);
        $target = Storage::disk($targetDiskName);
        $targetPath = UploadStorage::privatePath($sourcePath);

        if (! $source->exists($sourcePath)) {
            throw new RuntimeException('Public source object is missing.');
        }

        if (! $target->exists($targetPath)) {
            $stream = $source->readStream($sourcePath);
            if (! is_resource($stream)) {
                throw new RuntimeException('Public source object could not be opened.');
            }

            try {
                if (! $target->put($targetPath, $stream, 'private')) {
                    throw new RuntimeException('Private target object could not be written.');
                }
            } finally {
                fclose($stream);
            }
        }

        if ($source->size($sourcePath) !== $target->size($targetPath)) {
            throw new RuntimeException('Private target size does not match the public source.');
        }

        return $targetPath;
    }

    private function cleanupOriginals(File $file, string $publicDiskName, bool $keepSource): void
    {
        if ($keepSource) {
            return;
        }

        foreach (array_filter([$file->path, $file->thumbnail_path]) as $privatePath) {
            $sourcePath = substr($privatePath, strlen(UploadStorage::PRIVATE_PREFIX));
            if ($sourcePath !== '' && Storage::disk($publicDiskName)->exists($sourcePath)) {
                $this->deleteVerified($sourcePath, $publicDiskName);
                $this->cleaned++;
            }
        }
    }

    private function deleteVerified(?string $path, string $diskName): void
    {
        if (! $path) {
            return;
        }

        $disk = Storage::disk($diskName);
        if ($disk->exists($path) && (! $disk->delete($path) || $disk->exists($path))) {
            throw new RuntimeException('Public source object could not be removed.');
        }
    }
}
