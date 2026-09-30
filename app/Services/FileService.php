<?php

namespace App\Services;

use App\Models\File;
use App\Support\UploadStorage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileService
{
    public function __construct(private MediaOptimizer $mediaOptimizer) {}

    public function upload($user, UploadedFile $file, array $data): File
    {
        $data['user_id'] = $user->id;
        $optimized = $this->mediaOptimizer->store($file, $this->directoryFor($data));

        return File::create([
            'user_id' => $user->id,
            'club_id' => $data['club_id'] ?? null,
            'team_id' => $data['team_id'] ?? null,
            'event_id' => $data['event_id'] ?? null,
            'folder_id' => $data['folder_id'] ?? null,
            'display_name' => $this->displayNameForUpload($file),
            ...$optimized,
        ]);
    }

    public function delete(File $file): void
    {
        $this->deleteMany([$file]);
    }

    /**
     * @param iterable<File> $files
     */
    public function deleteMany(iterable $files): void
    {
        $fileIds = [];
        $paths = [];

        foreach ($files as $file) {
            if (! $file instanceof File) {
                continue;
            }

            $fileIds[] = $file->id;

            if ($file->path) {
                $paths[] = trim((string) $file->path);
            }

            if ($file->thumbnail_path) {
                $paths[] = trim((string) $file->thumbnail_path);
            }

            $file->delete();
        }

        $this->deleteDetachedPaths(array_values(array_filter(array_unique(array_map('intval', $fileIds), SORT_NUMERIC))), $paths);
    }

    private function deleteDetachedPaths(array $fileIds, array $paths): void
    {
        if (empty($fileIds)) {
            return;
        }

        $paths = array_values(array_filter(array_unique($paths), fn (string $path) => $path !== ''));
        if (empty($paths)) {
            return;
        }

        /** @var Collection<int, File> $usedFiles */
        $usedFiles = File::query()
            ->whereNotIn('id', $fileIds)
            ->where(function ($query) use ($paths) {
                $query->whereIn('path', $paths)
                    ->orWhereIn('thumbnail_path', $paths);
            })
            ->select('path', 'thumbnail_path')
            ->get();

        $usedPaths = [];

        foreach ($usedFiles as $usedFile) {
            if ($usedFile->path) {
                $usedPaths[$usedFile->path] = true;
            }

            if ($usedFile->thumbnail_path) {
                $usedPaths[$usedFile->thumbnail_path] = true;
            }
        }

        foreach ($paths as $path) {
            if (! isset($usedPaths[$path])) {
                Storage::disk(UploadStorage::disk($path))->delete($path);
            }
        }
    }

    private function directoryFor(array $data): string
    {
        if (! empty($data['event_id'])) {
            return 'events/'.$data['event_id'];
        }

        if (! empty($data['team_id'])) {
            return 'teams/'.$data['team_id'];
        }

        if (! empty($data['club_id'])) {
            return 'clubs/'.$data['club_id'];
        }

        return 'users/'.$data['user_id'];
    }

    private function displayNameForUpload(UploadedFile $file): string
    {
        $name = basename(str_replace('\\', '/', (string) $file->getClientOriginalName()));
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]+/', '', $name));

        return Str::limit($name !== '' ? $name : 'Datei', 180, '');
    }
}
