<?php

namespace App\Console\Commands;

use App\Models\Story;
use App\Support\UploadStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PruneExpiredStories extends Command
{
    protected $signature = 'airmius:prune-expired-stories {--dry-run : Show how many stories would be removed without deleting them}';

    protected $description = 'Delete expired stories and their stored media files.';

    public function handle(): int
    {
        $expiredStories = DB::table('stories')
            ->where('expires_at', '<=', now())
            ->get(['id', 'media_path', 'media_thumbnail_path']);
        $count = $expiredStories->count();

        if ($this->option('dry-run')) {
            $this->info($count.' expired stories would be pruned.');

            return self::SUCCESS;
        }

        $deleted = 0;

        $expiredStories->chunk(100)->each(function ($stories) use (&$deleted) {
            $ids = [];

            foreach ($stories as $story) {
                $ids[] = $story->id;

                $this->deletePath($story->media_path);

                if ($story->media_thumbnail_path) {
                    $this->deletePath($story->media_thumbnail_path);
                }
            }

            DB::table('stories')->whereIn('id', $ids)->delete();
            $deleted += count($ids);
        });

        $this->info($deleted.' expired stories pruned.');

        return self::SUCCESS;
    }

    private function deletePath(string $path): void
    {
        $disk = UploadStorage::disk();
        Storage::disk($disk)->delete($path);

        if ($disk !== 'public') {
            Storage::disk('public')->delete($path);
        }

        foreach (array_unique([$disk, 'public']) as $storageDisk) {
            try {
                $absolutePath = Storage::disk($storageDisk)->path($path);

                if (is_file($absolutePath)) {
                    @unlink($absolutePath);
                }
            } catch (\Throwable) {
                //
            }
        }

        $publicStoragePath = public_path('storage/'.ltrim($path, '/'));

        if (is_file($publicStoragePath)) {
            @unlink($publicStoragePath);
        }

        $localPublicPath = storage_path('app/public/'.ltrim($path, '/'));

        if (is_file($localPublicPath)) {
            @unlink($localPublicPath);
        }
    }
}
