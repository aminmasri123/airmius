<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;

class MinorSafety
{
    public const CONSENT_AGE = 16;

    public static function privacyDefaults(): array
    {
        return [
            'profile_visibility' => 'private',
            'direct_message_privacy' => 'friends',
            'friend_request_privacy' => 'friends',
            'ads_personalization_consent' => false,
            'ads_measurement_consent' => false,
            'product_analytics_consent' => false,
        ];
    }

    public static function isUnderConsentAge(?User $user): bool
    {
        return $user?->birth_date
            && self::birthDateRequiresGuardianConsent($user->birth_date);
    }

    public static function birthDateRequiresGuardianConsent(mixed $birthDate): bool
    {
        if (! $birthDate) {
            return false;
        }

        try {
            return Carbon::parse($birthDate)->age < self::CONSENT_AGE;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function hasResolvedGuardianConsent(?User $user): bool
    {
        if (! self::isUnderConsentAge($user)) {
            return true;
        }

        return (bool) $user?->guardian_consent_at
            && ! $user?->guardian_consent_rejected_at
            && ! $user?->guardian_consent_revoked_at;
    }

    public static function enforcePrivacyDefaults(User $user): void
    {
        if (! self::isUnderConsentAge($user)) {
            return;
        }

        $user->forceFill(self::privacyDefaults());

        if ($user->isDirty()) {
            $user->save();
        }
    }

    public static function canDirectMessage(?User $actor, ?User $recipient): bool
    {
        if (! $actor || ! $recipient) {
            return false;
        }

        if (! self::isUnderConsentAge($actor) && ! self::isUnderConsentAge($recipient)) {
            return true;
        }

        if (! self::hasResolvedGuardianConsent($actor) || ! self::hasResolvedGuardianConsent($recipient)) {
            return false;
        }

        return self::isGuardianPair($actor, $recipient)
            || $actor->isFriendsWith($recipient);
    }

    public static function canViewMinorProfile(User $profile, ?User $viewer): bool
    {
        if (! self::isUnderConsentAge($profile)) {
            return true;
        }

        return $viewer
            && (
                $viewer->id === $profile->id
                || $viewer->can('user.manage')
                || self::isGuardianPair($profile, $viewer)
                || $profile->isFriendsWith($viewer)
            );
    }

    public static function isGuardianPair(User $first, User $second): bool
    {
        return (int) $first->guardian_user_id === (int) $second->id
            || (int) $second->guardian_user_id === (int) $first->id;
    }
}
