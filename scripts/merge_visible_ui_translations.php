<?php

$root = dirname(__DIR__);
$langDirectory = $root.'/resources/js/lang';
$autoDirectory = $langDirectory.'/auto';
$progressDirectory = $root.'/storage/app/localization-progress';
$locales = ['de', 'en', 'fr', 'ar'];
$catalogs = [];
$autoCatalogs = [];

foreach ($locales as $locale) {
    $catalogs[$locale] = json_decode(
        file_get_contents("{$langDirectory}/{$locale}.json"),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    $autoCatalogs[$locale] = json_decode(
        file_get_contents("{$autoDirectory}/{$locale}.json"),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
}

$translations = [];
$legalTranslations = [];
foreach (['en', 'fr', 'ar'] as $locale) {
    $visiblePath = "{$progressDirectory}/airmius_local_translations_{$locale}.json";
    $legalPath = "{$progressDirectory}/airmius_legal_translations_{$locale}.json";
    if (! is_file($visiblePath) || ! is_file($legalPath)) {
        throw new RuntimeException("Missing completed visible or legal translation progress for {$locale}.");
    }

    $translations[$locale] = json_decode(file_get_contents($visiblePath), true, 512, JSON_THROW_ON_ERROR);
    $legalTranslations[$locale] = json_decode(file_get_contents($legalPath), true, 512, JSON_THROW_ON_ERROR);
}

$expectedSources = array_values(array_unique([
    ...array_keys($translations['en']),
    ...array_keys($translations['fr']),
    ...array_keys($translations['ar']),
]));
sort($expectedSources);
$visibleCorrections = [
    'en' => [],
    'fr' => [],
    'ar' => [
        'Dialog schliessen' => 'إغلاق مربع الحوار',
    ],
];

foreach ($expectedSources as $source) {
    $autoCatalogs['de']['auto'][$source] = $source;
    foreach (['en', 'fr', 'ar'] as $locale) {
        $translation = trim((string) (
            $visibleCorrections[$locale][$source]
            ?? $translations[$locale][$source]
            ?? $autoCatalogs[$locale]['auto'][$source]
            ?? $catalogs[$locale][$source]
            ?? ''
        ));
        if ($translation === '') {
            throw new RuntimeException("Empty {$locale} translation for: {$source}");
        }
        $autoCatalogs[$locale]['auto'][$source] = $translation;
    }
}

// German remains the sole source-key inventory for automatic visible-text translation.
$sourceAutoKeys = array_keys($autoCatalogs['de']['auto']);
foreach (['en', 'fr', 'ar'] as $locale) {
    $autoCatalogs[$locale]['auto'] = array_intersect_key(
        $autoCatalogs[$locale]['auto'],
        array_fill_keys($sourceAutoKeys, true),
    );
    foreach ($sourceAutoKeys as $key) {
        if (! array_key_exists($key, $autoCatalogs[$locale]['auto'])) {
            throw new RuntimeException("{$locale} is missing German auto source: {$key}");
        }
    }
}

foreach ($locales as $locale) {
    $json = json_encode(
        $autoCatalogs[$locale],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
    );
    // PHP uses four spaces for JSON_PRETTY_PRINT; the locale catalogs use two.
    $json = preg_replace_callback(
        '/^( +)/m',
        fn (array $match): string => str_repeat(' ', intdiv(strlen($match[1]), 2)),
        $json,
    );
    file_put_contents(
        "{$autoDirectory}/{$locale}.json",
        $json.PHP_EOL,
    );
}

$legalSources = array_values(array_unique([
    ...array_keys($legalTranslations['en']),
    ...array_keys($legalTranslations['fr']),
    ...array_keys($legalTranslations['ar']),
]));
sort($legalSources);
$legalCorrections = [
    'en' => [
        '10. Rechte betroffener Personen' => '10. Data subject rights',
        'Aktuell kannst du Werbe- und Mess-Einwilligungen in den Datenschutzeinstellungen deines Kontos verwalten.' => 'You can currently manage advertising and measurement consent in your account privacy settings.',
    ],
    'fr' => [
        '10. Kündigung und Kontolöschung' => '10. Résiliation et suppression du compte',
    ],
    'ar' => [],
];

$legalDirectory = $root.'/resources/legal';
if (! is_dir($legalDirectory) && ! mkdir($legalDirectory, 0775, true) && ! is_dir($legalDirectory)) {
    throw new RuntimeException("Could not create legal catalog directory: {$legalDirectory}");
}

foreach ($locales as $locale) {
    $messages = [];
    foreach ($legalSources as $source) {
        $translation = $locale === 'de'
            ? $source
            : trim((string) ($legalCorrections[$locale][$source] ?? $legalTranslations[$locale][$source] ?? ''));
        if ($translation === '') {
            throw new RuntimeException("Empty {$locale} legal translation for: {$source}");
        }
        $messages[$source] = $translation;
    }

    $json = json_encode([
        'contract' => 'localized-legal-content.v1',
        'source_locale' => 'de',
        'locale' => $locale,
        'messages' => $messages,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $json = preg_replace_callback(
        '/^( +)/m',
        fn (array $match): string => str_repeat(' ', intdiv(strlen($match[1]), 2)),
        $json,
    );
    file_put_contents("{$legalDirectory}/{$locale}.json", $json.PHP_EOL);
}

echo 'Merged '.count($expectedSources).' generated visible UI translations and '
    .count($legalSources)." server-only legal translations into DE, EN, FR and AR.\n";
