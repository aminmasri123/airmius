<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Notifications\GuardianConsentRequested;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

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
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'birth_date' => ['required', 'date', 'before_or_equal:today'],
            'guardian_email' => ['nullable', 'string', 'email', 'max:255', 'different:email'],
            'password' => $this->passwordRules(),
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
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

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'birth_date' => $birthDate->toDateString(),
            'guardian_email' => $requiresGuardianConsent ? $input['guardian_email'] : null,
            'guardian_consent_requested_at' => $requiresGuardianConsent ? now() : null,
            'guardian_consent_token' => $requiresGuardianConsent ? Str::random(64) : null,
            'password' => Hash::make($input['password']),
        ]);

        $user->assignRole($requiresGuardianConsent ? 'minor_pending_consent' : 'player');

        if ($requiresGuardianConsent) {
            try {
                Notification::send(
                    Notification::route('mail', $input['guardian_email']),
                    new GuardianConsentRequested($user)
                );
            } catch (\Throwable $exception) {
                Log::warning('Guardian consent notification could not be sent.', [
                    'user_id' => $user->id,
                    'guardian_email' => $input['guardian_email'],
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return $user;
    }
}
