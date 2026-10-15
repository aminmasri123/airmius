<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class UploadStorage
{
    public const PRIVATE_PREFIX = 'private-files/';

    public static function disk(?string $path = null): string
    {
        if ($path && str_starts_with($path, 'private-post-media/')) {
            return 'local';
        }

        if (static::isPrivate($path)) {
            return (string) config('filesystems.private_uploads_disk', 'local');
        }

        return config('filesystems.uploads_disk', 'public');
    }

    public static function privateDisk(): string
    {
        return (string) config('filesystems.private_uploads_disk', 'local');
    }

    public static function privatePath(string $path): string
    {
        return static::PRIVATE_PREFIX.ltrim($path, '/');
    }

    public static function isPrivate(?string $path): bool
    {
        return is_string($path) && str_starts_with($path, static::PRIVATE_PREFIX);
    }

    public static function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'private-post-media/')) {
            [, $postId, $name] = explode('/', $path, 3);

            return route('api.v1.posts.media', ['post' => $postId, 'name' => $name]);
        }

        if (static::isPrivate($path)) {
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
