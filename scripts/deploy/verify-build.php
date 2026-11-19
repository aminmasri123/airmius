<?php

declare(strict_types=1);

$manifestPath = $argv[1] ?? '';
if (! is_file($manifestPath)) {
    throw new RuntimeException('Vite manifest is missing.');
}
$manifest = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
if (! is_array($manifest) || ! isset($manifest['resources/js/app.js'])) {
    throw new RuntimeException('Application entry is missing from the Vite manifest.');
}
$base = realpath(dirname($manifestPath));
foreach ($manifest as $entry) {
    foreach (array_merge([$entry['file'] ?? ''], $entry['css'] ?? [], $entry['assets'] ?? []) as $asset) {
        $resolved = realpath($base.'/'.$asset);
        if ($asset === '' || $resolved === false || ! str_starts_with($resolved, $base.DIRECTORY_SEPARATOR) || ! is_file($resolved)) {
            throw new RuntimeException('Missing or invalid Vite asset: '.$asset);
        }
    }
    foreach (array_merge($entry['imports'] ?? [], $entry['dynamicImports'] ?? []) as $import) {
        if (! isset($manifest[$import])) {
            throw new RuntimeException('Missing Vite import: '.$import);
        }
    }
}
echo "Vite manifest and all referenced assets verified.\n";
