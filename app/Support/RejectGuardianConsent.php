<?php

namespace App\Support;

use App\Models\User;

class RejectGuardianConsent
{
    public static function apply(User $child): void
    {
        abort_if(MinorSafety::hasResolvedGuardianConsent($child), 422, __('guardian.validation.already_approved'));

        $child->forceFill([
            'guardian_consent_at' => null,
            'guardian_consent_rejected_at' => now(),
            'guardian_consent_revoked_at' => null,
            'guardian_consent_revoked_by_email' => null,
            'guardian_consent_token' => null,
            ...MinorSafety::privacyDefaults(),
        ])->save();

        if ($child->hasRole('minor_player')) {
            $child->removeRole('minor_player');
        }
        if (! $child->hasRole('minor_pending_consent')) {
            $child->assignRole('minor_pending_consent');
        }
    }

    public static function notify(User $child): void
    {
        AppNotification::sendLocalized(
            $child,
            'guardian.consent_rejected',
            'guardian.notifications.rejected_title',
            'guardian.notifications.rejected_body',
            data: ['minor_id' => $child->id, 'url' => route('guardian-consent.pending')],
        );
    }
}
