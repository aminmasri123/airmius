<?php

namespace App\Services;

use App\Support\SupportedLocale;
use Illuminate\Support\Facades\App;

final class LegalContentLocalizer
{
    public const CONTRACT = 'localized-legal-content.v1';

    /** @var array<string, array<string, string>> */
    private static array $catalogs = [];

    private bool $complete = true;

    /**
     * @param  array<int, array{title: string, body: array<int, string>}>  $sections
     * @param  array<string, string>|null  $action
     * @param  array<int, string>  $protectedValues
     * @return array<string, mixed>
     */
    public function localize(
        string $title,
        array $sections,
        ?string $note = null,
        ?array $action = null,
        array $protectedValues = [],
    ): array {
        $locale = SupportedLocale::normalize(App::getLocale()) ?? SupportedLocale::DEFAULT;
        $this->complete = true;
        $protectedValues = $this->normalizeProtectedValues($protectedValues);

        $localizedSections = array_map(fn (array $section): array => [
            'title' => $this->translate($section['title'], $locale, $protectedValues),
            'body' => array_map(
                fn (string $line): string => $this->translate($line, $locale, $protectedValues),
                $section['body'],
            ),
        ], $sections);

        $localizedAction = $action;
        foreach (['label', 'authenticated_label'] as $labelKey) {
            if (is_string($localizedAction[$labelKey] ?? null)) {
                $localizedAction[$labelKey] = $this->translate($localizedAction[$labelKey], $locale, $protectedValues);
            }
        }
        if ($locale !== SupportedLocale::DEFAULT) {
            foreach (['href', 'authenticated_href'] as $hrefKey) {
                if (is_string($localizedAction[$hrefKey] ?? null)) {
                    $localizedAction[$hrefKey] = $this->withLocale($localizedAction[$hrefKey], $locale);
                }
            }
        }

        return [
            'title' => $this->translate($title, $locale, $protectedValues),
            'sections' => $localizedSections,
            'note' => $note === null ? null : $this->translate($note, $locale, $protectedValues),
            'action' => $localizedAction,
            'contentLocale' => $locale,
            'sourceLocale' => SupportedLocale::DEFAULT,
            'textDirection' => SupportedLocale::direction($locale),
            'translationComplete' => $this->complete,
            'translationContract' => self::CONTRACT,
            'externalReviewRequired' => true,
        ];
    }

    /** @param array<int, string> $protectedValues */
    private function translate(string $source, string $locale, array $protectedValues): string
    {
        if ($locale === SupportedLocale::DEFAULT || $source === '') {
            return $source;
        }

        if (in_array($source, $protectedValues, true)) {
            return $source;
        }

        $catalog = $this->catalog($locale);
        if (isset($catalog[$source]) && trim($catalog[$source]) !== '') {
            return $catalog[$source];
        }

        if ($protectedValues === []) {
            $this->complete = false;

            return $source;
        }

        $pattern = '/('.implode('|', array_map(
            fn (string $value): string => preg_quote($value, '/'),
            $protectedValues,
        )).')/u';
        $parts = preg_split($pattern, $source, -1, PREG_SPLIT_DELIM_CAPTURE);

        if (! is_array($parts) || count($parts) === 1) {
            $this->complete = false;

            return $source;
        }

        return implode('', array_map(function (string $part) use ($catalog, $protectedValues): string {
            if ($part === '' || in_array($part, $protectedValues, true)) {
                return $part;
            }

            preg_match('/^(\s*)(.*?)(\s*)$/us', $part, $matches);
            $leading = $matches[1] ?? '';
            $core = $matches[2] ?? $part;
            $trailing = $matches[3] ?? '';

            if ($core === '' || ! preg_match('/[\p{L}§]/u', $core)) {
                return $part;
            }

            $translation = $catalog[$core] ?? null;
            if (! is_string($translation) || trim($translation) === '') {
                $this->complete = false;

                return $part;
            }

            return $leading.$translation.$trailing;
        }, $parts));
    }

    /** @return array<string, string> */
    private function catalog(string $locale): array
    {
        if (isset(self::$catalogs[$locale])) {
            return self::$catalogs[$locale];
        }

        $path = resource_path("legal/{$locale}.json");
        $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
        $catalog = $decoded['messages'] ?? null;

        if (($decoded['contract'] ?? null) !== self::CONTRACT
            || ($decoded['source_locale'] ?? null) !== SupportedLocale::DEFAULT
            || ($decoded['locale'] ?? null) !== $locale
            || ! is_array($catalog)) {
            return self::$catalogs[$locale] = [];
        }

        return self::$catalogs[$locale] = array_filter(
            $catalog,
            fn (mixed $value, mixed $key): bool => is_string($key) && is_string($value),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, string>
     */
    private function normalizeProtectedValues(array $values): array
    {
        $values = array_values(array_unique(array_filter(
            $values,
            fn (mixed $value): bool => is_string($value)
                && trim($value) !== ''
                && trim($value) !== 'Nicht angegeben',
        )));

        usort($values, fn (string $left, string $right): int => mb_strlen($right) <=> mb_strlen($left));

        return $values;
    }

    private function withLocale(string $href, string $locale): string
    {
        if (preg_match('/(?:\?|&)locale=/u', $href)) {
            return $href;
        }

        [$url, $fragment] = array_pad(explode('#', $href, 2), 2, null);
        $url .= str_contains($url, '?') ? '&' : '?';
        $url .= 'locale='.rawurlencode($locale);

        return $fragment === null ? $url : $url.'#'.$fragment;
    }
}
