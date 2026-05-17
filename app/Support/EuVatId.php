<?php

namespace App\Support;

class EuVatId
{
    private const PATTERNS = [
        'AT' => '/^ATU\d{8}$/',
        'BE' => '/^BE[01]?\d{9}$/',
        'BG' => '/^BG\d{9,10}$/',
        'CY' => '/^CY\d{8}[A-Z]$/',
        'CZ' => '/^CZ\d{8,10}$/',
        'DE' => '/^DE\d{9}$/',
        'DK' => '/^DK\d{8}$/',
        'EE' => '/^EE\d{9}$/',
        'EL' => '/^EL\d{9}$/',
        'ES' => '/^ES[A-Z0-9]\d{7}[A-Z0-9]$/',
        'FI' => '/^FI\d{8}$/',
        'FR' => '/^FR[A-Z0-9]{2}\d{9}$/',
        'HR' => '/^HR\d{11}$/',
        'HU' => '/^HU\d{8}$/',
        'IE' => '/^IE([A-Z0-9]\d{7}[A-Z]|[A-Z0-9]\d{7}[A-Z]{2}|\d[A-Z0-9]\d{5}[A-Z])$/',
        'IT' => '/^IT\d{11}$/',
        'LT' => '/^LT(\d{9}|\d{12})$/',
        'LU' => '/^LU\d{8}$/',
        'LV' => '/^LV\d{11}$/',
        'MT' => '/^MT\d{8}$/',
        'NL' => '/^NL\d{9}B\d{2}$/',
        'PL' => '/^PL\d{10}$/',
        'PT' => '/^PT\d{9}$/',
        'RO' => '/^RO\d{2,10}$/',
        'SE' => '/^SE\d{12}$/',
        'SI' => '/^SI\d{8}$/',
        'SK' => '/^SK\d{10}$/',
    ];

    public static function normalize(?string $vatId): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) $vatId));
    }

    public static function countryCode(?string $vatId): ?string
    {
        $normalized = self::normalize($vatId);

        return strlen($normalized) >= 2 ? substr($normalized, 0, 2) : null;
    }

    public static function looksValid(?string $vatId): bool
    {
        $normalized = self::normalize($vatId);
        $country = self::countryCode($normalized);

        if (! $country || ! isset(self::PATTERNS[$country])) {
            return false;
        }

        return (bool) preg_match(self::PATTERNS[$country], $normalized);
    }
}
