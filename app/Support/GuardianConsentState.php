<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class GuardianConsentState
{
    public static function sync(User $user): void
    {
        if (! $user->birth_date || $user->guardian_consent_at) {
            return;
        }

        $birthDate = Carbon::parse($user->birth_date);
        if ($birthDate->age >= 16 || blank($user->guardian_email)) {
            return;
        }

        $shouldNotify = ! $user->guardian_consent_requested_at || ! $user->guardian_consent_token;

        $user->forceFill([
            'guardian_consent_requested_at' => $user->guardian_consent_requested_at ?: now(),
            'guardian_consent_rejected_at' => null,
            'guardian_consent_token' => $user->guardian_consent_token ?: Str::random(64),
        ])->save();

        if (! $user->hasRole('minor_pending_consent')) {
            $user->syncRoles(['minor_pending_consent']);
        }

        if ($shouldNotify) {
            GuardianConsentNotifier::send($user, $user->guardian_email);
        }
    }
}
