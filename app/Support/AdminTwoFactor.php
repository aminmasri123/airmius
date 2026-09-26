<?php

namespace App\Support;

use App\Models\User;

class AdminTwoFactor
{
    public const ERROR_CODE = 'admin_two_factor_required';
    public const MESSAGE = 'Für Plattform-Admins ist Zwei-Faktor-Authentifizierung Pflicht. Bitte aktiviere 2FA in den Einstellungen.';
    public const STEP_UP_ERROR_CODE = 'admin_step_up_required';
    public const STEP_UP_MESSAGE = 'Bitte bestätige deine Identität erneut, bevor du diese sensible Admin-Aktion ausführst.';
    public const STEP_UP_TOKEN_ABILITY = 'admin-step-up';
    public const STEP_UP_WINDOW_SECONDS = 900;

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

    public static function freshWebStepUp(?int $confirmedAt): bool
    {
        return $confirmedAt !== null
            && $confirmedAt >= now()->subSeconds(self::STEP_UP_WINDOW_SECONDS)->getTimestamp();
    }

    public static function tokenAbility(): string
    {
        return self::STEP_UP_TOKEN_ABILITY.':'.now()->getTimestamp();
    }

    public static function freshTokenStepUp(?User $user): bool
    {
        $token = $user?->currentAccessToken();

        if (! $token || ! method_exists($token, 'can')) {
            return false;
        }

        if (! property_exists($token, 'abilities')) {
            return $token->can(self::STEP_UP_TOKEN_ABILITY);
        }

        $threshold = now()->subSeconds(self::STEP_UP_WINDOW_SECONDS)->getTimestamp();

        foreach ((array) ($token->abilities ?? []) as $ability) {
            if (! is_string($ability) || ! str_starts_with($ability, self::STEP_UP_TOKEN_ABILITY.':')) {
                continue;
            }

            $confirmedAt = (int) substr($ability, strlen(self::STEP_UP_TOKEN_ABILITY) + 1);

            if ($confirmedAt >= $threshold) {
                return true;
            }
        }

        return false;
    }
}
