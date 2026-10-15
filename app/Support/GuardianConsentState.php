<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

class GuardianConsentState
{
    public static function sync(User $user): void
    {
        if (! MinorSafety::isUnderConsentAge($user)) {
            self::completeAgeTransition($user);

            return;
        }

        MinorSafety::enforcePrivacyDefaults($user);

        if ($user->guardian_consent_at || blank($user->guardian_email)) {
            return;
        }

        $shouldNotify = ! $user->guardian_consent_requested_at || ! $user->guardian_consent_token;

        $user->forceFill([
            'guardian_consent_requested_at' => $user->guardian_consent_requested_at ?: now(),
            'guardian_consent_rejected_at' => null,
            'guardian_consent_token' => $user->guardian_consent_token ?: Str::random(64),
            'guardian_consent_version' => config('guardian.consent_version'),
        ])->save();

        if (! $user->hasRole('minor_pending_consent')) {
            $user->syncRoles(['minor_pending_consent']);
        }

        if ($shouldNotify) {
            GuardianConsentNotifier::send($user, $user->guardian_email);
        }
    }

    private static function completeAgeTransition(User $user): void
    {
        if (! $user->hasRole('minor_pending_consent')) {
            return;
        }

        $user->forceFill([
            'guardian_consent_token' => null,
            'guardian_consent_rejected_at' => null,
            'guardian_consent_revoked_at' => null,
            'guardian_consent_revoked_by_email' => null,
        ])->save();

        $user->removeRole('minor_pending_consent');

        if (! $user->roles()->exists()) {
            $user->assignRole('player');
        }
    }
}
