#!/usr/bin/env php
<?php

declare(strict_types=1);

$base = rtrim($argv[1] ?? 'https://airmius.com', '/');
$parts = parse_url($base);
$local = in_array($parts['host'] ?? null, ['127.0.0.1', 'localhost'], true);
if (! is_array($parts)
    || ! in_array($parts['scheme'] ?? null, $local ? ['http', 'https'] : ['https'], true)
    || ! isset($parts['host'])
    || isset($parts['user'])
    || isset($parts['pass'])
    || isset($parts['query'])
    || isset($parts['fragment'])) {
    fwrite(STDERR, "Use an HTTPS origin or a loopback HTTP origin without credentials or query parameters.\n");
    exit(2);
}

function fetchGuide(string $url, string $accept): array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Accept: {$accept}\r\n",
            'timeout' => 15,
            'follow_location' => 0,
            'ignore_errors' => true,
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $body = @file_get_contents($url, false, $context);
    $headers = $http_response_header ?? [];
    preg_match('/^HTTP\/\S+ (\d{3})/', $headers[0] ?? '', $match);

    return [(int) ($match[1] ?? 0), $body === false ? '' : $body, $headers];
}

$failed = 0;
foreach (['verein-loeschen' => 7, 'konto-loeschen' => 8] as $path => $minimumSections) {
    [$status, , $headers] = fetchGuide("{$base}/{$path}", 'text/html');
    $htmlOk = $status === 200
        && str_contains(strtolower(implode(' ', $headers)), 'text/html');
    printf("%s HTML: %s\n", $path, $htmlOk ? 'OK' : 'FAIL');
    $failed += (int) ! $htmlOk;

    foreach (['de', 'en', 'fr', 'ar'] as $locale) {
        [$status, $body] = fetchGuide("{$base}/{$path}?format=json&locale={$locale}", 'application/json');
        $data = json_decode($body, true);
        $sections = is_array($data) ? ($data['sections'] ?? null) : null;
        $text = is_array($sections)
            ? implode(' ', array_merge(...array_map(
                fn ($section) => is_array($section) && is_array($section['body'] ?? null) ? $section['body'] : [],
                $sections,
            )))
            : '';
        $ok = $status === 200
            && ($data['contentLocale'] ?? null) === $locale
            && ($data['translationComplete'] ?? null) === true
            && is_array($sections)
            && count($sections) >= $minimumSections
            && str_contains($text, '§ 147')
            && str_contains($text, '§ 257')
            && str_contains($text, '§ 14b')
            && ($path !== 'verein-loeschen' || str_contains($text, '30'));
        printf("%s %s JSON: %s\n", $path, $locale, $ok ? 'OK' : 'FAIL');
        $failed += (int) ! $ok;
    }
}

exit($failed === 0 ? 0 : 1);
