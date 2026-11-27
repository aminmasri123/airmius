<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;

class LocalDateTime
{
    public const DEFAULT_TIMEZONE = 'UTC';

    public static function timezoneFor(?User $user = null, ?Request $request = null): string
    {
        $timezone = $request?->header('X-Airmius-Timezone')
            ?: $user?->timezone
            ?: config('app.timezone', self::DEFAULT_TIMEZONE);

        return self::isValidTimezone($timezone) ? $timezone : self::DEFAULT_TIMEZONE;
    }

    public static function format(?CarbonInterface $dateTime, ?User $user = null, ?Request $request = null, string $format = 'd.m.Y H.i'): ?string
    {
        return $dateTime?->copy()
            ->timezone(self::timezoneFor($user, $request))
            ->format($format);
    }

    public static function todayFor(?User $user = null, ?Request $request = null): string
    {
        // Native clients can report their current offset without an IANA zone.
        // Apply it to UTC, not to the server's configured timezone.
        $offset = $request?->header('X-Airmius-Utc-Offset-Minutes');
        if (is_string($offset) && preg_match('/^-?\d{1,3}$/D', $offset)
            && (int) $offset >= -720 && (int) $offset <= 840) {
            return now('UTC')->addMinutes((int) $offset)->toDateString();
        }

        return now(self::timezoneFor($user, $request))->toDateString();
    }

    public static function isValidTimezone(mixed $timezone): bool
    {
        return is_string($timezone)
            && in_array($timezone, timezone_identifiers_list(), true);
    }
}
