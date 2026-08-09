<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\OrganizationJobInterest;
use App\Models\User;
use App\Support\MinorSafety;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserPrivacyRightsService
{
    private const CONSENT_FIELDS = [
        'ads_personalization' => 'ads_personalization_consent',
        'ads_measurement' => 'ads_measurement_consent',
        'product_analytics' => 'product_analytics_consent',
    ];

    private const RECRUITING_PROFILE_SHARING = 'recruiting_profile_sharing';

    public function correct(User $user, array $data): User
    {
        $fields = $this->correctionFields($data);

        if ($fields === []) {
            return $user->refresh();
        }

        return DB::transaction(function () use ($user, $fields) {
            $currentFirstName = $user->first_name;
            $currentLastName = $user->last_name;

            if (array_key_exists('first_name', $fields)) {
                $currentFirstName = trim((string) $fields['first_name']);
            }

            if (array_key_exists('last_name', $fields)) {
                $currentLastName = trim((string) $fields['last_name']);
            }

            if (array_key_exists('first_name', $fields) || array_key_exists('last_name', $fields)) {
                $fields['name'] = trim($currentFirstName.' '.$currentLastName);
            }

            if (array_key_exists('email', $fields) && ! hash_equals((string) $user->email, (string) $fields['email'])) {
                $fields['email_verified_at'] = null;
            }

            if (array_key_exists('country', $fields)) {
                $fields['country'] = Str::upper((string) $fields['country']);
            }

            if (MinorSafety::birthDateRequiresGuardianConsent($fields['birth_date'] ?? $user->birth_date)) {
                $fields = array_merge($fields, MinorSafety::privacyDefaults());
            }

            $user->forceFill($fields)->save();

            Activity::create([
                'user_id' => $user->id,
                'type' => 'privacy.profile_corrected',
                'data' => [
                    'fields' => array_values(array_diff(array_keys($fields), ['email_verified_at', 'name'])),
                    'corrected_at' => now()->toJSON(),
                ],
            ]);

            return $user->refresh();
        });
    }

    public function withdrawConsents(User $user, array $consents = []): array
    {
        $normalized = collect($consents)
            ->map(fn ($consent) => Str::lower((string) $consent))
            ->filter()
            ->unique()
            ->values();

        $selected = $normalized->isEmpty() || $normalized->contains('all')
            ? [...array_keys(self::CONSENT_FIELDS), self::RECRUITING_PROFILE_SHARING]
            : $normalized->filter(fn (string $consent) => array_key_exists($consent, self::CONSENT_FIELDS)
                || $consent === self::RECRUITING_PROFILE_SHARING)->values()->all();

        if ($selected === []) {
            return [];
        }

        DB::transaction(function () use ($user, $selected) {
            $updates = [];

            foreach ($selected as $consent) {
                if (isset(self::CONSENT_FIELDS[$consent])) {
                    $updates[self::CONSENT_FIELDS[$consent]] = false;
                }
            }

            if ($updates !== []) {
                $user->forceFill($updates)->save();
            }
            if (in_array(self::RECRUITING_PROFILE_SHARING, $selected, true)) {
                OrganizationJobInterest::query()
                    ->where('user_id', $user->id)
                    ->whereNotNull('profile_consent_at')
                    ->update([
                        'shared_profile_fields' => null,
                        'profile_consent_at' => null,
                    ]);
            }

            Activity::create([
                'user_id' => $user->id,
                'type' => 'privacy.consent_withdrawn',
                'data' => [
                    'consents' => array_values($selected),
                    'withdrawn_at' => now()->toJSON(),
                ],
            ]);
        });

        return array_values($selected);
    }

    public static function supportedConsents(): array
    {
        return [...array_keys(self::CONSENT_FIELDS), self::RECRUITING_PROFILE_SHARING];
    }

    private function correctionFields(array $data): array
    {
        $allowed = [
            'first_name',
            'last_name',
            'email',
            'country',
            'street',
            'house_number',
            'postal_code',
            'city',
            'state',
            'birth_date',
            'gender',
            'bio',
            'guardian_email',
            'athlete_license_number',
            'profile_visibility',
            'direct_message_privacy',
            'friend_request_privacy',
        ];

        return collect($data)
            ->only($allowed)
            ->mapWithKeys(function ($value, string $key) {
                if (is_string($value)) {
                    $value = trim($value);
                }

                return [$key => $value === '' ? null : $value];
            })
            ->all();
    }
}
