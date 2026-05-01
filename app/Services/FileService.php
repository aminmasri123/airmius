<?php

namespace App\Services;

use App\Models\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FileService
{
    public function upload($user, UploadedFile $file, array $data): File
    {
        $data['user_id'] = $user->id;
        $path = $file->store($this->directoryFor($data), 'public');

        return File::create([
            'user_id' => $user->id,
            'club_id' => $data['club_id'] ?? null,
            'team_id' => $data['team_id'] ?? null,
            'event_id' => $data['event_id'] ?? null,
            'folder_id' => $data['folder_id'] ?? null,
            'path' => $path,
            'type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    public function delete(File $file): void
    {
        $path = $file->path;
        $file->delete();

        if (! File::where('path', $path)->exists()) {
            Storage::disk('public')->delete($path);
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
