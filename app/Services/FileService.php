<?php

namespace App\Services;

use App\Models\File;

class FileService
{
    public function upload($user, $file, $clubId)
    {
        $path = $file->store("clubs/$clubId");

        return File::create([
            'user_id' => $user->id,
            'club_id' => $clubId,
            'path' => $path,
            'type' => $file->getClientMimeType(),
        ]);
    }
}
