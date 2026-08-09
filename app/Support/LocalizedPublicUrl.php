<?php

namespace App\Support;

final class LocalizedPublicUrl
{
    public const SITEMAP_PROTOCOL_URL_LIMIT = 50000;

    public const SITEMAP_MAX_BASE_URLS = 12000;

    public const SITEMAP_MAX_BLOG_POSTS = 5000;

    public const SITEMAP_MAX_LEARNING_COURSES = 5000;

    public const SITEMAP_MAX_BLOG_CATEGORIES = 500;

    /** @return array<int, array{hreflang: string, href: string}> */
    public static function alternates(string $url): array
    {
        $baseUrl = self::forLocale($url, SupportedLocale::DEFAULT);
        $alternates = collect(SupportedLocale::ALL)
            ->map(fn (string $locale): array => [
                'hreflang' => $locale,
                'href' => self::forLocale($baseUrl, $locale),
            ])
            ->all();

        $alternates[] = [
            'hreflang' => 'x-default',
            'href' => $baseUrl,
        ];

        return $alternates;
    }

    public static function forLocale(string $url, mixed $locale): string
    {
        $locale = SupportedLocale::normalize($locale) ?? SupportedLocale::DEFAULT;
        $url = explode('#', trim($url), 2)[0];
        [$baseUrl, $query] = array_pad(explode('?', $url, 2), 2, '');

        parse_str($query, $parameters);
        unset($parameters['locale']);

        if ($locale !== SupportedLocale::DEFAULT) {
            $parameters['locale'] = $locale;
        }

        $query = http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);

        return $query === '' ? $baseUrl : "{$baseUrl}?{$query}";
    }

    /** @return array<int, string> */
    public static function openGraphAlternates(mixed $locale): array
    {
        $current = SupportedLocale::normalize($locale) ?? SupportedLocale::DEFAULT;

        return collect(SupportedLocale::ALL)
            ->reject(fn (string $candidate): bool => $candidate === $current)
            ->map(fn (string $candidate): string => self::openGraphLocale($candidate))
            ->values()
            ->all();
    }

    public static function openGraphLocale(mixed $locale): string
    {
        return match (SupportedLocale::normalize($locale)) {
            'en' => 'en_US',
            'fr' => 'fr_FR',
            'ar' => 'ar_AR',
            default => 'de_DE',
        };
    }

    public static function sitemapMaxLocalizedUrls(): int
    {
        return self::SITEMAP_MAX_BASE_URLS * count(SupportedLocale::ALL);
    }
}
