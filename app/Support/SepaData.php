<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Validator;

/** Shared checks for stored master data and the final bank instruction. */
final class SepaData
{
    // ISO 13616 national lengths (SWIFT IBAN registry), including SEPA countries.
    private const IBAN_LENGTHS = [
        'AD' => 24, 'AE' => 23, 'AL' => 28, 'AT' => 20, 'AZ' => 28, 'BA' => 20, 'BE' => 16,
        'BG' => 22, 'BH' => 22, 'BI' => 27, 'BR' => 29, 'BY' => 28, 'CH' => 21, 'CR' => 22,
        'CY' => 28, 'CZ' => 24, 'DE' => 22, 'DJ' => 27, 'DK' => 18, 'DO' => 28, 'EE' => 20,
        'EG' => 29, 'ES' => 24, 'FI' => 18, 'FK' => 18, 'FO' => 18, 'FR' => 27, 'GB' => 22,
        'GE' => 22, 'GI' => 23, 'GL' => 18, 'GR' => 27, 'GT' => 28, 'HR' => 21, 'HU' => 28,
        'IE' => 22, 'IL' => 23, 'IQ' => 23, 'IS' => 26, 'IT' => 27, 'JO' => 30, 'KW' => 30,
        'KZ' => 20, 'LB' => 28, 'LC' => 32, 'LI' => 21, 'LT' => 20, 'LU' => 20, 'LV' => 21,
        'LY' => 25, 'MC' => 27, 'MD' => 24, 'ME' => 22, 'MK' => 19, 'MN' => 20, 'MR' => 27,
        'MT' => 31, 'MU' => 30, 'NI' => 28, 'NL' => 18, 'NO' => 15, 'OM' => 23, 'PK' => 24,
        'PL' => 28, 'PS' => 29, 'PT' => 25, 'QA' => 29, 'RO' => 24, 'RS' => 22, 'RU' => 33,
        'SA' => 24, 'SC' => 31, 'SD' => 18, 'SE' => 24, 'SI' => 19, 'SK' => 24, 'SM' => 27,
        'SO' => 23, 'ST' => 25, 'SV' => 28, 'TL' => 23, 'TN' => 24, 'TR' => 26, 'UA' => 29,
        'VA' => 22, 'VG' => 24, 'XK' => 20,
    ];

    public static function validIban(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }
        $value = ClubMembershipInput::normalizeIban($value) ?? '';

        return preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/D', $value) === 1
            && (self::IBAN_LENGTHS[substr($value, 0, 2)] ?? 0) === strlen($value)
            && self::checksum(substr($value, 4).substr($value, 0, 4));
    }

    public static function validBic(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/D', ClubMembershipInput::normalizeBic($value) ?? '') === 1;
    }

    public static function validCreditorId(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }
        $value = ClubMembershipInput::normalizeIban($value) ?? '';
        if (! preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{3}[A-Z0-9]{1,28}$/D', $value)) {
            return false;
        }
        if (str_starts_with($value, 'DE') && ! preg_match('/^DE[0-9]{2}[A-Z0-9]{3}0[0-9]{10}$/D', $value)) {
            return false;
        }

        // The three-character business code does not participate in MOD 97.
        return self::checksum(substr($value, 7).substr($value, 0, 4));
    }

    public static function rule(string $type): Closure
    {
        static $rules = [];

        return $rules[$type] ??= static function (string $attribute, mixed $value, Closure $fail) use ($type): void {
            $valid = match ($type) {
                'iban' => self::validIban($value),
                'bic' => self::validBic($value),
                'creditor_id' => self::validCreditorId($value),
                'reference' => is_string($value) && preg_match("~^[A-Za-z0-9+?/'():,. -]{1,35}$~D", $value) === 1 && trim($value) !== '',
            };
            if (! $valid) {
                $fail(__('sepa.invalid_'.$type));
            }
        };
    }

    public static function mandateRules(string $prefix = ''): array
    {
        $required = 'required_if:'.$prefix.'sepa_mandate_active,true';

        return [
            $prefix.'sepa_iban' => ['nullable', $required, 'string', self::rule('iban')],
            $prefix.'sepa_bic' => ['nullable', 'string', self::rule('bic')],
            $prefix.'sepa_mandate_reference' => ['nullable', $required, 'string', self::rule('reference')],
            $prefix.'sepa_mandate_signed_on' => ['nullable', $required, 'date_format:Y-m-d', 'before_or_equal:today'],
            $prefix.'sepa_mandate_active' => ['boolean'],
        ];
    }

    public static function mandateReady(array $data): bool
    {
        return (bool) ($data['sepa_mandate_active'] ?? false)
            && Validator::make($data, self::mandateRules())->passes();
    }

    public static function assertCreditor(array $data): void
    {
        Validator::make($data, [
            'sepa_creditor_id' => ['required', 'string', self::rule('creditor_id')],
            'sepa_iban' => ['required', 'string', self::rule('iban')],
            'sepa_bic' => ['nullable', 'string', self::rule('bic')],
            'sepa_account_holder' => ['required', 'string', 'max:120'],
        ])->validate();
    }

    private static function checksum(string $value): bool
    {
        $remainder = 0;
        foreach (str_split($value) as $character) {
            $digits = ctype_digit($character) ? $character : (string) (ord($character) - 55);
            foreach (str_split($digits) as $digit) {
                $remainder = ($remainder * 10 + (int) $digit) % 97;
            }
        }

        return $remainder === 1;
    }
}
