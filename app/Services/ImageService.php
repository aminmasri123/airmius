<?php

namespace App\Services;

use App\Support\UploadStorage;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ImageService
{
    public function upload($file, $path, $type = 'avatar')
    {
       $config = config("image.$type");

$manager = new ImageManager(new Driver());

// Hauptbild
$image = $manager->read($file)
    ->resize($config['width'], null)
    ->toJpeg($config['quality']);

Storage::disk(UploadStorage::disk())->put($path, (string) $image, 'public');

// Thumbnail
if (isset($config['thumb'])) {

    $thumbPath = str_replace('.jpg', '_thumb.jpg', $path);

    $thumbnail = $manager->read($file)
        ->cover($config['thumb'], $config['thumb']) // ✅ FIX
        ->toJpeg($config['quality']);

    Storage::disk(UploadStorage::disk())->put($thumbPath, (string) $thumbnail, 'public');
}

return $path;
    }
}
