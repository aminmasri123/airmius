<?php

declare(strict_types=1);

$sourcePath = $argv[1] ?? dirname(__DIR__).'/public/img/logo/Airmius-Mark.png';
$outputDirectory = $argv[2] ?? dirname(__DIR__).'/public/img/logo';

if (! extension_loaded('gd')) {
    fwrite(STDERR, "The GD extension is required to build PWA icons.\n");
    exit(1);
}

if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
    fwrite(STDERR, "The source logo is missing or unreadable.\n");
    exit(1);
}

if (! is_dir($outputDirectory) || ! is_writable($outputDirectory)) {
    fwrite(STDERR, "The output directory is missing or not writable.\n");
    exit(1);
}

$source = imagecreatefrompng($sourcePath);
if (! $source) {
    fwrite(STDERR, "The source logo is not a valid PNG image.\n");
    exit(1);
}

/** @return array{x: int, y: int, width: int, height: int} */
$visibleBounds = static function (GdImage $image): array {
    $width = imagesx($image);
    $height = imagesy($image);
    $minX = $width;
    $minY = $height;
    $maxX = -1;
    $maxY = -1;

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $alpha = (imagecolorat($image, $x, $y) >> 24) & 0x7F;

            if ($alpha >= 126) {
                continue;
            }

            $minX = min($minX, $x);
            $minY = min($minY, $y);
            $maxX = max($maxX, $x);
            $maxY = max($maxY, $y);
        }
    }

    if ($maxX < $minX || $maxY < $minY) {
        throw new RuntimeException('The source logo has no visible pixels.');
    }

    return [
        'x' => $minX,
        'y' => $minY,
        'width' => $maxX - $minX + 1,
        'height' => $maxY - $minY + 1,
    ];
};

/** @param array{x: int, y: int, width: int, height: int} $bounds */
$render = static function (
    GdImage $source,
    array $bounds,
    int $size,
    float $coverage,
    string $outputPath,
    bool $maskable,
): void {
    $canvas = imagecreatetruecolor($size, $size);
    if (! $canvas) {
        throw new RuntimeException("Could not allocate the {$size}px icon canvas.");
    }

    if ($maskable) {
        $background = imagecolorallocate($canvas, 7, 16, 29);
        imagefill($canvas, 0, 0, $background);
        imagealphablending($canvas, true);
    } else {
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);
    }

    $maximum = (int) floor($size * $coverage);
    $scale = min($maximum / $bounds['width'], $maximum / $bounds['height']);
    $targetWidth = max(1, (int) round($bounds['width'] * $scale));
    $targetHeight = max(1, (int) round($bounds['height'] * $scale));
    $targetX = (int) floor(($size - $targetWidth) / 2);
    $targetY = (int) floor(($size - $targetHeight) / 2);

    imagecopyresampled(
        $canvas,
        $source,
        $targetX,
        $targetY,
        $bounds['x'],
        $bounds['y'],
        $targetWidth,
        $targetHeight,
        $bounds['width'],
        $bounds['height'],
    );

    if (! imagepng($canvas, $outputPath, 9)) {
        throw new RuntimeException("Could not write {$outputPath}.");
    }

    imagedestroy($canvas);
};

try {
    $bounds = $visibleBounds($source);

    foreach ([180, 192, 512] as $size) {
        $render(
            $source,
            $bounds,
            $size,
            0.84,
            "{$outputDirectory}/Airmius-PWA-{$size}.png",
            false,
        );
    }

    foreach ([192, 512] as $size) {
        $render(
            $source,
            $bounds,
            $size,
            0.58,
            "{$outputDirectory}/Airmius-PWA-Maskable-{$size}.png",
            true,
        );
    }
} catch (Throwable $exception) {
    imagedestroy($source);
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
}

imagedestroy($source);
fwrite(STDOUT, "PWA icons generated from the existing Airmius mark.\n");
