<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class UploadStorage
{
    public static function disk(): string
    {
        return config('filesystems.uploads_disk', 'public');
    }

    public static function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $baseUrl = rtrim((string) config('filesystems.uploads_url'), '/');

        if ($baseUrl !== '') {
            return $baseUrl.'/'.ltrim($path, '/');
        }

        return Storage::disk(static::disk())->url($path);
    }
}
