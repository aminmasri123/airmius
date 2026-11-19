<?php

namespace App\Services\Learning;

use App\Models\LearningCourse;
use App\Support\UploadStorage;
use Illuminate\Support\Facades\Storage;

class CertificateLogo
{
    public function bytes(LearningCourse $course): ?string
    {
        $directory = 'learning/certificate-logos/'.$course->user_id.'/';
        $prefix = UploadStorage::url($directory);
        $url = (string) $course->certificate_logo_url;
        if (! $prefix || ! str_starts_with($url, $prefix)) {
            return null;
        }
        $filename = substr($url, strlen($prefix));
        if (! preg_match('/^[a-f0-9-]+\.(?:webp|png|jpg|jpeg)$/i', $filename)) {
            return null;
        }
        $disk = Storage::disk(UploadStorage::disk());
        $path = $directory.$filename;
        if (! $disk->exists($path) || $disk->size($path) > 5 * 1024 * 1024) {
            return null;
        }

        return $disk->get($path);
    }
}
