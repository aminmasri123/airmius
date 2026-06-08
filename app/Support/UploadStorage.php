<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

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

        if (str_starts_with($path, '/')) {
            return URL::to($path);
        }

        $baseUrl = rtrim((string) config('filesystems.uploads_url'), '/');

        if ($baseUrl !== '') {
            return $baseUrl.'/'.ltrim($path, '/');
        }

        $url = Storage::disk(static::disk())->url($path);

        return str_starts_with($url, '/') ? URL::to($url) : $url;
    }
}
