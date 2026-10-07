<?php

namespace App\Support;

use Carbon\Carbon;

class ClubMembershipInput
{
    public const MEMBERSHIP_STATUSES = ['active', 'non_member', 'pending', 'paused', 'former'];

    public const CONTRIBUTION_INTERVALS = ['none', 'monthly', 'quarterly', 'four_monthly', 'semi_yearly', 'yearly', 'once'];

    public static function normalizeKey(mixed $value): string
    {
        $key = strtolower(trim((string) $value));
        $key = strtr($key, [
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
            'pruefung' => 'pending',
            'in_pruefung' => 'pending',
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
            'viermonatlich' => 'four_monthly',
            'alle_4_monate' => 'four_monthly',
            'halbjaehrlich' => 'semi_yearly',
            'halbjahr' => 'semi_yearly',
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
        $interval = $data['contribution_interval'] ?? 'none';
        if ($interval === 'none') {
            return null;
        }

        if (filled($data['contribution_next_invoice_on'] ?? null)) {
            return Carbon::parse($data['contribution_next_invoice_on'])->toDateString();
        }

        if (! filled($data['contribution_amount'] ?? null) || (float) $data['contribution_amount'] <= 0) {
            return null;
        }

        $joinedOn = filled($data['joined_on'] ?? null)
            ? Carbon::parse($data['joined_on'])->startOfDay()
            : now()->startOfDay();

        return match ($interval) {
            'monthly' => $joinedOn->copy()->startOfMonth()->toDateString(),
            'quarterly' => Carbon::create($joinedOn->year, ((int) floor(($joinedOn->month - 1) / 3) * 3) + 1, 1)->toDateString(),
            'four_monthly' => Carbon::create(
                $joinedOn->year,
                $joinedOn->month <= 4 ? 1 : ($joinedOn->month <= 8 ? 5 : 9),
                1
            )->toDateString(),
            'semi_yearly' => Carbon::create($joinedOn->year, $joinedOn->month <= 6 ? 1 : 7, 1)->toDateString(),
            'yearly' => $joinedOn->copy()->startOfYear()->toDateString(),
            'once' => $joinedOn->toDateString(),
            default => null,
        };
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
