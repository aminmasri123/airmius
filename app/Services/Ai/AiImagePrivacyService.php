<?php

namespace App\Services\Ai;

use Illuminate\Http\UploadedFile;
use RuntimeException;

class AiImagePrivacyService
{
    public function prepareForVision(UploadedFile $image): array
    {
        if (! function_exists('imagecreatefromstring')) {
            throw new RuntimeException('Bildverarbeitung ist auf dem Server nicht aktiviert.');
        }

        $contents = @file_get_contents($image->getRealPath());

        if (! is_string($contents) || $contents === '') {
            throw new RuntimeException('Bild konnte nicht gelesen werden.');
        }

        $source = @imagecreatefromstring($contents);

        if (! $source) {
            throw new RuntimeException('Bildformat konnte nicht verarbeitet werden.');
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $maxDimension = max(256, (int) config('airmius_ai.privacy.max_image_dimension', 1024));
        $scale = min(1, $maxDimension / max($sourceWidth, $sourceHeight));
        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

        ob_start();
        imagejpeg($target, null, max(50, min(95, (int) config('airmius_ai.privacy.jpeg_quality', 82))));
        $jpeg = ob_get_clean();

        imagedestroy($source);
        imagedestroy($target);

        if (! is_string($jpeg) || $jpeg === '') {
            throw new RuntimeException('Bild konnte nicht fuer die KI vorbereitet werden.');
        }

        return [
            'mime' => 'image/jpeg',
            'base64' => base64_encode($jpeg),
            'bytes' => strlen($jpeg),
            'width' => $targetWidth,
            'height' => $targetHeight,
            'original_mime' => $image->getMimeType(),
            'original_size' => $image->getSize(),
            'privacy' => [
                'exif_removed' => true,
                'resized' => $scale < 1,
                'stored_upload' => false,
            ],
        ];
    }
}
