<?php

namespace App\Http\Controllers;

use App\Support\AccountType;
use App\Support\GuardianConsentNotifier;
use App\Support\MinorSafety;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ProfileCompletionController extends Controller
{
    public function edit(Request $request)
    {
        return Inertia::render('Auth/CompleteProfile', [
            'user' => [
                ...$request->user()->only(['first_name', 'last_name', 'email', 'country', 'gender', 'guardian_email']),
                'birth_date' => $request->user()->birth_date?->toDateString(),
            ],
            'accountType' => AccountType::normalize($request->session()->get('social_auth.account_type')),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'country' => ['required', 'string', 'size:2'],
            'birth_date' => ['required', 'date', 'before_or_equal:today'],
            'gender' => ['required', 'string', Rule::in(['female', 'male', 'diverse', 'not_specified'])],
            'account_type' => ['required', Rule::in(AccountType::VALUES)],
            'guardian_email' => ['nullable', 'string', 'email', 'max:255', 'different:email'],
        ]);

        $birthDate = Carbon::parse($data['birth_date']);
        $user = $request->user();
        if ($user->hasRole('minor_pending_consent')
            && ($birthDate->age >= 16 || ($user->birth_date && ! $birthDate->isSameDay($user->birth_date)))) {
            throw ValidationException::withMessages([
                'birth_date' => __('account_security.errors.pending_consent_birth_date'),
            ]);
        }
        $requiresGuardianConsent = $birthDate->age < 16;
        $guardianEmail = $requiresGuardianConsent ? mb_strtolower(trim($data['guardian_email'] ?? '')) : null;

        if ($requiresGuardianConsent && empty($data['guardian_email'])) {
            return back()->withErrors([
                'guardian_email' => 'Bei Nutzern unter 16 Jahren ist die E-Mail eines Erziehungsberechtigten erforderlich.',
            ]);
        }

        $user->update([
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'name' => trim($data['first_name'].' '.$data['last_name']),
            'country' => strtoupper($data['country']),
            'birth_date' => $birthDate->toDateString(),
            'gender' => $data['gender'],
            'guardian_email' => $guardianEmail,
            'guardian_consent_requested_at' => $requiresGuardianConsent ? now() : null,
            'guardian_consent_token' => $requiresGuardianConsent ? Str::random(64) : null,
            'guardian_consent_version' => $requiresGuardianConsent ? config('guardian.consent_version') : null,
            ...($requiresGuardianConsent ? MinorSafety::privacyDefaults() : []),
        ]);

        if ($requiresGuardianConsent) {
            AccountType::assignInitialRole($user, AccountType::ATHLETE, true);

            GuardianConsentNotifier::send($user, $guardianEmail);

            return redirect()->route('guardian-consent.pending');
        }

        if (! $user->roles()->exists() || $user->hasRole('minor_pending_consent')) {
            AccountType::assignInitialRole($user, $data['account_type'], false);
        }

        $request->session()->forget('social_auth.account_type');

        return redirect()->route('auth.home')->with('success', 'Profil vervollständigt.');
    }
}
