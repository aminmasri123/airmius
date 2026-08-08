<?php

namespace App\Support;

final class SupportedLocale
{
    public const ALL = ['de', 'en', 'fr', 'ar'];

    public const DEFAULT = 'de';

    public const RTL = ['ar'];

    public static function normalize(mixed $locale): ?string
    {
        if (! is_string($locale) || trim($locale) === '') {
            return null;
        }

        $locale = strtolower(trim(explode(',', $locale)[0] ?? ''));
        $locale = strtolower(trim(explode(';', $locale)[0] ?? ''));
        $locale = str_replace('_', '-', $locale);
        $locale = substr($locale, 0, 2);

        return in_array($locale, self::ALL, true) ? $locale : null;
    }

    public static function direction(mixed $locale): string
    {
        return in_array(self::normalize($locale), self::RTL, true) ? 'rtl' : 'ltr';
    }
}
