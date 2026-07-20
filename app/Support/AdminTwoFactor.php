<?php

namespace App\Support;

use App\Models\User;

class AdminTwoFactor
{
    public const ERROR_CODE = 'admin_two_factor_required';
    public const MESSAGE = 'Fuer Plattform-Admins ist Zwei-Faktor-Authentifizierung Pflicht. Bitte aktiviere 2FA in den Einstellungen.';

    public static function requiredFor(?User $user): bool
    {
        return (bool) $user?->hasAnyRole(Roles::FULL_ACCESS);
    }

    public static function enabledFor(?User $user): bool
    {
        return filled($user?->two_factor_secret)
            && filled($user?->two_factor_confirmed_at);
    }

    public static function missingFor(?User $user): bool
    {
        return self::requiredFor($user) && ! self::enabledFor($user);
    }
}
