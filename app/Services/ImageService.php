<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ImageService
{
    public function upload($file, string $path, string $type = 'avatar'): string
    {
        $config = config("image.$type");
        $disk = config('jetstream.profile_photo_disk', 'public');
        $manager = new ImageManager(new Driver());

        $image = $manager->read($file)
            ->resize($config['width'], null)
            ->toJpeg($config['quality']);

        Storage::disk($disk)->put($path, (string) $image, 'public');

        if (isset($config['thumb'])) {
            $thumbPath = str_replace('.jpg', '_thumb.jpg', $path);
            $thumbnail = $manager->read($file)
                ->cover($config['thumb'], $config['thumb'])
                ->toJpeg($config['quality']);

            Storage::disk($disk)->put($thumbPath, (string) $thumbnail, 'public');
        }

        return $path;
    }
}
