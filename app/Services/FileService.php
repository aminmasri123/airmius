<?php

namespace App\Services;

use App\Models\File;
use App\Support\UploadStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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
            ...$optimized,
        ]);
    }

    public function delete(File $file): void
    {
        $paths = array_filter([$file->path, $file->thumbnail_path]);
        $file->delete();

        foreach ($paths as $path) {
            if (! File::where('path', $path)->orWhere('thumbnail_path', $path)->exists()) {
                Storage::disk(UploadStorage::disk())->delete($path);
            }
        }
    }

    private function directoryFor(array $data): string
    {
        if (!empty($data['event_id'])) {
            return 'events/'.$data['event_id'];
        }

        if (!empty($data['team_id'])) {
            return 'teams/'.$data['team_id'];
        }

        if (!empty($data['club_id'])) {
            return 'clubs/'.$data['club_id'];
        }

        return 'users/'.$data['user_id'];
    }
}
