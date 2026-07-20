<?php

$root = dirname(__DIR__);
$langDirectory = $root.'/resources/js/lang';
$progressDirectory = $root.'/storage/app/localization-progress';
$locales = ['de', 'en', 'fr', 'ar'];
$catalogs = [];

foreach ($locales as $locale) {
    $catalogs[$locale] = json_decode(
        file_get_contents("{$langDirectory}/{$locale}.json"),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
}

$translations = [];
foreach (['en', 'fr', 'ar'] as $locale) {
    $path = "{$progressDirectory}/airmius_local_translations_{$locale}.json";
    if (! is_file($path)) {
        throw new RuntimeException("Missing completed translation progress: {$path}");
    }
    $translations[$locale] = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
}

$expectedSources = array_values(array_unique([
    ...array_keys($translations['en']),
    ...array_keys($translations['fr']),
    ...array_keys($translations['ar']),
]));
sort($expectedSources);

foreach ($expectedSources as $source) {
    $catalogs['de']['auto'][$source] = $source;
    foreach (['en', 'fr', 'ar'] as $locale) {
        $translation = trim((string) (
            $translations[$locale][$source]
            ?? $catalogs[$locale]['auto'][$source]
            ?? $catalogs[$locale][$source]
            ?? ''
        ));
        if ($translation === '') {
            throw new RuntimeException("Empty {$locale} translation for: {$source}");
        }
        $catalogs[$locale]['auto'][$source] = $translation;
    }
}

// German remains the sole source-key inventory for automatic visible-text translation.
$sourceAutoKeys = array_keys($catalogs['de']['auto']);
foreach (['en', 'fr', 'ar'] as $locale) {
    $catalogs[$locale]['auto'] = array_intersect_key(
        $catalogs[$locale]['auto'],
        array_fill_keys($sourceAutoKeys, true),
    );
    foreach ($sourceAutoKeys as $key) {
        if (! array_key_exists($key, $catalogs[$locale]['auto'])) {
            throw new RuntimeException("{$locale} is missing German auto source: {$key}");
        }
    }
}

foreach ($locales as $locale) {
    $json = json_encode(
        $catalogs[$locale],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
    );
    // PHP uses four spaces for JSON_PRETTY_PRINT; the locale catalogs use two.
    $json = preg_replace_callback(
        '/^( +)/m',
        fn (array $match): string => str_repeat(' ', intdiv(strlen($match[1]), 2)),
        $json,
    );
    file_put_contents(
        "{$langDirectory}/{$locale}.json",
        $json.PHP_EOL,
    );
}

echo 'Merged '.count($expectedSources)." generated visible UI translations into DE, EN, FR and AR.\n";
