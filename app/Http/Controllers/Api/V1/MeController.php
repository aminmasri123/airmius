<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Identity\UpdateUserLanguage;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Support\AccountType;
use App\Support\GuardianConsentNotifier;
use App\Support\GuardianConsentState;
use App\Support\MinorSafety;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MeController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        GuardianConsentState::sync($user);
        $user->loadCount(['followers', 'following', 'posts']);

        return new UserResource(
            $user->loadMissing([
                'roles',
                'permissions',
                'clubs',
                'teams.club',
                'sportProfiles.sport',
            ])
        );
    }

    public function updateLanguage(Request $request, UpdateUserLanguage $updateLanguage)
    {
        $data = $request->validate([
            'language' => ['required', Rule::in(['de', 'en', 'fr', 'ar'])],
        ]);

        return new UserResource(
            $updateLanguage->execute($request->user(), $data['language'])
        );
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'country' => ['required', 'string', 'size:2'],
            'street' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:40'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['required', 'date', 'before_or_equal:today'],
            'gender' => ['required', 'string', Rule::in(['female', 'male', 'diverse', 'not_specified'])],
            'account_type' => ['nullable', Rule::in(AccountType::VALUES)],
            'bio' => ['nullable', 'string', 'max:1000'],
            'guardian_email' => ['nullable', 'string', 'email', 'max:255', 'different:email'],
        ]);

        $birthDate = Carbon::parse($data['birth_date']);
        $requiresGuardianConsent = $birthDate->age < 16;
        $guardianEmail = $requiresGuardianConsent ? mb_strtolower(trim((string) ($data['guardian_email'] ?? ''))) : null;

        if ($requiresGuardianConsent && $guardianEmail === '') {
            return response()->json([
                'message' => 'Bei Nutzern unter 16 Jahren ist die E-Mail eines Erziehungsberechtigten erforderlich.',
                'errors' => [
                    'guardian_email' => ['Bei Nutzern unter 16 Jahren ist die E-Mail eines Erziehungsberechtigten erforderlich.'],
                ],
            ], 422);
        }

        $user = $request->user();
        $user->update([
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'name' => trim($data['first_name'].' '.$data['last_name']),
            'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            'country' => strtoupper($data['country']),
            'street' => $data['street'] ?? null,
            'house_number' => $data['house_number'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'birth_date' => $birthDate->toDateString(),
            'gender' => $data['gender'],
            'bio' => array_key_exists('bio', $data) ? $data['bio'] : $user->bio,
            'guardian_email' => $guardianEmail,
            'guardian_consent_requested_at' => $requiresGuardianConsent ? now() : null,
            'guardian_consent_rejected_at' => null,
            'guardian_consent_token' => $requiresGuardianConsent ? Str::random(64) : null,
            ...($requiresGuardianConsent ? MinorSafety::privacyDefaults() : []),
        ]);

        if ($requiresGuardianConsent) {
            AccountType::assignInitialRole($user, AccountType::ATHLETE, true);
            GuardianConsentNotifier::send($user, $guardianEmail);
        } elseif ($user->hasRole('minor_pending_consent')) {
            AccountType::assignInitialRole(
                $user,
                AccountType::normalize($data['account_type'] ?? null),
                false,
            );
        } elseif (! $user->roles()->exists()) {
            AccountType::assignInitialRole(
                $user,
                AccountType::normalize($data['account_type'] ?? null),
                false,
            );
        }

        return new UserResource($user->refresh()->loadMissing([
            'roles',
            'permissions',
            'clubs',
            'teams.club',
            'sportProfiles.sport',
        ]));
    }
}
