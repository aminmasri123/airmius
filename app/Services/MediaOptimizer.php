<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaOptimizer
{
    private const IMAGE_MAX_WIDTH = 1600;
    private const IMAGE_MAX_HEIGHT = 1600;
    private const IMAGE_WEBP_QUALITY = 82;
    private const VIDEO_MAX_HEIGHT = 720;
    private const VIDEO_CRF = 28;

    public function store(UploadedFile $file, string $directory): array
    {
        $mime = $file->getClientMimeType();

        if ($this->isCompressibleImage($mime)) {
            return $this->storeImage($file, $directory);
        }

        if ($this->isVideo($mime)) {
            return $this->storeVideo($file, $directory);
        }

        return $this->storeOriginal($file, $directory);
    }

    private function storeImage(UploadedFile $file, string $directory): array
    {
        $source = $this->imageResource($file->getRealPath(), $file->getClientMimeType());

        if (! $source) {
            return $this->storeOriginal($file, $directory);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        [$targetWidth, $targetHeight] = $this->containedSize($width, $height, self::IMAGE_MAX_WIDTH, self::IMAGE_MAX_HEIGHT);

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        $path = trim($directory, '/').'/'.Str::uuid().'.webp';
        $absolutePath = Storage::disk('public')->path($path);

        if (! is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0775, true);
        }

        imagewebp($target, $absolutePath, self::IMAGE_WEBP_QUALITY);

        imagedestroy($source);
        imagedestroy($target);

        return [
            'path' => $path,
            'type' => 'image/webp',
            'size' => filesize($absolutePath) ?: $file->getSize(),
        ];
    }

    private function storeVideo(UploadedFile $file, string $directory): array
    {
        $ffmpeg = $this->findExecutable('ffmpeg');

        if (! $ffmpeg) {
            return $this->storeOriginal($file, $directory);
        }

        $path = trim($directory, '/').'/'.Str::uuid().'.mp4';
        $absolutePath = Storage::disk('public')->path($path);

        if (! is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0775, true);
        }

        $command = [
            $ffmpeg,
            '-y',
            '-i',
            $file->getRealPath(),
            '-vf',
            'scale=-2:min('.self::VIDEO_MAX_HEIGHT.'\,ih)',
            '-c:v',
            'libx264',
            '-preset',
            'veryfast',
            '-crf',
            (string) self::VIDEO_CRF,
            '-c:a',
            'aac',
            '-b:a',
            '128k',
            '-movflags',
            '+faststart',
            $absolutePath,
        ];

        if (! $this->runProcess($command, 300) || ! file_exists($absolutePath) || filesize($absolutePath) === 0) {
            if (file_exists($absolutePath)) {
                unlink($absolutePath);
            }

            return $this->storeOriginal($file, $directory);
        }

        return [
            'path' => $path,
            'type' => 'video/mp4',
            'size' => filesize($absolutePath) ?: $file->getSize(),
        ];
    }

    private function storeOriginal(UploadedFile $file, string $directory): array
    {
        $path = $file->store($directory, 'public');

        return [
            'path' => $path,
            'type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ];
    }

    private function isCompressibleImage(?string $mime): bool
    {
        return in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)
            && function_exists('imagewebp');
    }

    private function isVideo(?string $mime): bool
    {
        return str_starts_with((string) $mime, 'video/');
    }

    private function imageResource(string $path, string $mime)
    {
        return match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png' => imagecreatefrompng($path),
            'image/webp' => imagecreatefromwebp($path),
            default => null,
        };
    }

    private function containedSize(int $width, int $height, int $maxWidth, int $maxHeight): array
    {
        $ratio = min($maxWidth / $width, $maxHeight / $height, 1);

        return [
            max(1, (int) round($width * $ratio)),
            max(1, (int) round($height * $ratio)),
        ];
    }

    private function findExecutable(string $name): ?string
    {
        $command = PHP_OS_FAMILY === 'Windows' ? "where {$name}" : "command -v {$name}";
        $output = [];
        $exitCode = 1;

        @exec($command, $output, $exitCode);

        if ($exitCode !== 0 || empty($output[0])) {
            return null;
        }

        return trim($output[0]);
    }

    private function runProcess(array $command, int $timeout): bool
    {
        $pipes = [];
        $process = @proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (! is_resource($process)) {
            return false;
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $startedAt = time();

        do {
            $status = proc_get_status($process);
            stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);

            if (! $status['running']) {
                break;
            }

            if (time() - $startedAt > $timeout) {
                proc_terminate($process);
                break;
            }

            usleep(100000);
        } while (true);

        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($process) === 0;
    }
}
