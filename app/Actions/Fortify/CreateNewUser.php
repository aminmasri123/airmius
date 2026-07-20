<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Notifications\AccountWelcomeNotification;
use App\Support\GuardianConsentNotifier;
use App\Support\MinorSafety;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $input['gender'] = filled($input['gender'] ?? null) ? $input['gender'] : 'not_specified';

        Validator::make($input, [
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'country' => ['required', 'string', 'size:2'],
            'street' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:40'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['required', 'date', 'before_or_equal:today'],
            'gender' => ['required', 'string', Rule::in(['female', 'male', 'diverse', 'not_specified'])],
            'guardian_email' => ['nullable', 'string', 'email', 'max:255', 'different:email'],
            'password' => $this->passwordRules(),
            'terms' => ['accepted', 'required'],
        ], [
            'email.unique' => 'Dieses Konto existiert bereits. Bitte melde dich an oder nutze Passwort vergessen.',
        ])->after(function ($validator) use ($input) {
            if (! isset($input['birth_date'])) {
                return;
            }

            try {
                $birthDate = Carbon::parse($input['birth_date']);
            } catch (\Throwable) {
                return;
            }

            if ($birthDate->age < 16 && empty($input['guardian_email'])) {
                $validator->errors()->add(
                    'guardian_email',
                    'Bei Nutzern unter 16 Jahren ist die E-Mail eines Erziehungsberechtigten erforderlich.'
                );
            }
        })->validate();

        $birthDate = Carbon::parse($input['birth_date']);
        $requiresGuardianConsent = $birthDate->age < 16;
        $guardianEmail = $requiresGuardianConsent ? mb_strtolower(trim($input['guardian_email'])) : null;
        $firstName = trim($input['first_name']);
        $lastName = trim($input['last_name']);

        $user = User::create([
            'name' => trim($firstName.' '.$lastName),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $input['email'],
            'country' => strtoupper($input['country']),
            'street' => $input['street'] ?? null,
            'house_number' => $input['house_number'] ?? null,
            'postal_code' => $input['postal_code'] ?? null,
            'city' => $input['city'] ?? null,
            'state' => $input['state'] ?? null,
            'birth_date' => $birthDate->toDateString(),
            'gender' => $input['gender'],
            'guardian_email' => $guardianEmail,
            'guardian_consent_requested_at' => $requiresGuardianConsent ? now() : null,
            'guardian_consent_token' => $requiresGuardianConsent ? Str::random(64) : null,
            ...($requiresGuardianConsent ? MinorSafety::privacyDefaults() : []),
            'password' => Hash::make($input['password']),
        ]);

        $user->assignRole($requiresGuardianConsent ? 'minor_pending_consent' : 'player');

        try {
            $user->notify(new AccountWelcomeNotification());
        } catch (\Throwable $exception) {
            Log::warning('Account welcome notification could not be sent.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'exception' => $exception->getMessage(),
            ]);
        }

        if ($requiresGuardianConsent) {
            GuardianConsentNotifier::send($user, $guardianEmail);
        }

        app(\App\Http\Controllers\CommerceCheckoutController::class)->trackAttributedAdConversion(request(), 'registration', 0, [
            'registered_user_id' => $user->id,
            'country' => $user->country,
        ]);

        return $user;
    }
}
