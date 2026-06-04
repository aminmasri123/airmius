<?php

namespace App\Support;

use Carbon\Carbon;

class ClubMembershipInput
{
    public const MEMBERSHIP_STATUSES = ['active', 'non_member', 'pending', 'paused', 'former'];

    public const CONTRIBUTION_INTERVALS = ['none', 'monthly', 'quarterly', 'yearly', 'once'];

    public static function normalizeKey(mixed $value): string
    {
        $key = strtolower(trim((string) $value));
        $key = strtr($key, [
            'ä' => 'ae',
            'ö' => 'oe',
            'ü' => 'ue',
            'ß' => 'ss',
            'ä' => 'ae',
            'ö' => 'oe',
            'ü' => 'ue',
            'ß' => 'ss',
            ' ' => '_',
            '-' => '_',
            '/' => '_',
        ]);

        return preg_replace('/[^a-z0-9_]/', '', $key) ?: '';
    }

    public static function normalizeMembershipStatus(mixed $value): string
    {
        $status = self::normalizeKey($value);
        $aliases = [
            'vereinsmitglied' => 'active',
            'mitglied' => 'active',
            'aktiv' => 'active',
            'pausiert' => 'paused',
            'pause' => 'paused',
            'kein_mitglied' => 'non_member',
            'nichtmitglied' => 'non_member',
            'Prüfung' => 'pending',
            'in_Prüfung' => 'pending',
            'wartend' => 'pending',
            'ehemalig' => 'former',
        ];

        return in_array($status, self::MEMBERSHIP_STATUSES, true) ? $status : ($aliases[$status] ?? 'active');
    }

    public static function normalizeContributionInterval(mixed $value): string
    {
        $interval = self::normalizeKey($value);
        $aliases = [
            'kein_beitrag' => 'none',
            'keiner' => 'none',
            'monatlich' => 'monthly',
            'monat' => 'monthly',
            'quartal' => 'quarterly',
            'vierteljaehrlich' => 'quarterly',
            'jaehrlich' => 'yearly',
            'jahr' => 'yearly',
            'einmalig' => 'once',
        ];

        return in_array($interval, self::CONTRIBUTION_INTERVALS, true) ? $interval : ($aliases[$interval] ?? 'none');
    }

    public static function normalizeMoney(mixed $value): ?float
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $value = preg_replace('/[^0-9,.\-]/', '', $value);
        if ($value === '' || $value === '-') {
            return null;
        }

        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');
        if ($lastComma !== false && ($lastDot === false || $lastComma > $lastDot)) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }

    public static function normalizeDate(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function normalizedNextInvoiceDate(array $data): ?string
    {
        if (($data['contribution_interval'] ?? 'none') === 'none') {
            return null;
        }

        return $data['contribution_next_invoice_on'] ?? null;
    }

    public static function normalizeIban(mixed $value): ?string
    {
        $iban = strtoupper(preg_replace('/\s+/', '', (string) $value));

        return $iban !== '' ? $iban : null;
    }

    public static function normalizeBic(mixed $value): ?string
    {
        $bic = strtoupper(preg_replace('/\s+/', '', (string) $value));

        return $bic !== '' ? $bic : null;
    }

    public static function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'ja', 'yes', 'true', 'aktiv', 'active'], true);
    }
}
