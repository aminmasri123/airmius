<?php

$root = dirname(__DIR__);
$langDirectory = $root.'/resources/js/lang';
$autoDirectory = $langDirectory.'/auto';
$locales = ['de', 'en', 'fr', 'ar'];

if (! is_dir($autoDirectory) && ! mkdir($autoDirectory, 0775, true) && ! is_dir($autoDirectory)) {
    throw new RuntimeException("Could not create locale auto-catalog directory: {$autoDirectory}");
}

/** Encode JSON with the two-space indentation used by the frontend catalogs. */
function encodeCatalog(array $catalog): string
{
    $json = json_encode(
        $catalog,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
    );

    return preg_replace_callback(
        '/^( +)/m',
        fn (array $match): string => str_repeat(' ', intdiv(strlen($match[1]), 2)),
        $json,
    ).PHP_EOL;
}

/**
 * Remove one top-level JSON property while retaining the original formatting
 * and order of every unrelated locale entry.
 */
function withoutTopLevelProperty(string $json, string $property): string
{
    $matched = preg_match('/^  "'.preg_quote($property, '/').'"\s*:\s*/m', $json, $match, PREG_OFFSET_CAPTURE);
    if ($matched !== 1) {
        return $json;
    }

    $propertyStart = $match[0][1];
    $valueStart = $propertyStart + strlen($match[0][0]);
    $opening = $json[$valueStart] ?? '';
    if (! in_array($opening, ['{', '['], true)) {
        throw new RuntimeException("Expected {$property} to contain a JSON object or array.");
    }

    $closing = $opening === '{' ? '}' : ']';
    $depth = 0;
    $inString = false;
    $escaped = false;
    $length = strlen($json);
    $valueEnd = null;

    for ($index = $valueStart; $index < $length; $index++) {
        $character = $json[$index];

        if ($inString) {
            if ($escaped) {
                $escaped = false;
            } elseif ($character === '\\') {
                $escaped = true;
            } elseif ($character === '"') {
                $inString = false;
            }

            continue;
        }

        if ($character === '"') {
            $inString = true;
        } elseif ($character === $opening) {
            $depth++;
        } elseif ($character === $closing) {
            $depth--;
            if ($depth === 0) {
                $valueEnd = $index + 1;
                break;
            }
        }
    }

    if ($valueEnd === null) {
        throw new RuntimeException("Could not find the end of {$property}.");
    }

    $removeEnd = $valueEnd;
    while ($removeEnd < $length && in_array($json[$removeEnd], [' ', "\t"], true)) {
        $removeEnd++;
    }
    if (($json[$removeEnd] ?? '') === ',') {
        $removeEnd++;
    }
    if (($json[$removeEnd] ?? '') === "\r") {
        $removeEnd++;
    }
    if (($json[$removeEnd] ?? '') === "\n") {
        $removeEnd++;
    }

    return substr($json, 0, $propertyStart).substr($json, $removeEnd);
}

foreach ($locales as $locale) {
    $corePath = "{$langDirectory}/{$locale}.json";
    $autoPath = "{$autoDirectory}/{$locale}.json";
    $raw = (string) file_get_contents($corePath);
    $catalog = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

    if (! array_key_exists('auto', $catalog)) {
        if (! is_file($autoPath)) {
            throw new RuntimeException("{$locale} has neither an embedded nor a split automatic catalog.");
        }

        echo "{$locale}: already split.\n";
        continue;
    }

    $auto = $catalog['auto'];
    $patterns = $catalog['auto_patterns'] ?? [];
    if (! is_array($auto) || ! is_array($patterns)) {
        throw new RuntimeException("{$locale} automatic locale data is malformed.");
    }

    $core = withoutTopLevelProperty($raw, 'auto');
    $core = withoutTopLevelProperty($core, 'auto_patterns');
    json_decode($core, true, 512, JSON_THROW_ON_ERROR);

    file_put_contents($corePath, $core);
    file_put_contents($autoPath, encodeCatalog([
        'auto' => $auto,
        'auto_patterns' => $patterns,
    ]));

    echo "{$locale}: split ".count($auto)." automatic strings and ".count($patterns)." patterns.\n";
}
