<?php

namespace App\Http\Controllers;

use App\Notifications\GuardianConsentRequested;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ProfileCompletionController extends Controller
{
    public function edit(Request $request)
    {
        return Inertia::render('Auth/CompleteProfile', [
            'user' => $request->user()->only(['first_name', 'last_name', 'email', 'country', 'birth_date', 'guardian_email']),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'country' => ['required', 'string', 'size:2'],
            'birth_date' => ['required', 'date', 'before_or_equal:today'],
            'guardian_email' => ['nullable', 'string', 'email', 'max:255', 'different:email'],
        ]);

        $birthDate = Carbon::parse($data['birth_date']);
        $requiresGuardianConsent = $birthDate->age < 16;

        if ($requiresGuardianConsent && empty($data['guardian_email'])) {
            return back()->withErrors([
                'guardian_email' => 'Bei Nutzern unter 16 Jahren ist die E-Mail eines Erziehungsberechtigten erforderlich.',
            ]);
        }

        $user = $request->user();
        $user->update([
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'name' => trim($data['first_name'].' '.$data['last_name']),
            'country' => strtoupper($data['country']),
            'birth_date' => $birthDate->toDateString(),
            'guardian_email' => $requiresGuardianConsent ? $data['guardian_email'] : null,
            'guardian_consent_requested_at' => $requiresGuardianConsent ? now() : null,
            'guardian_consent_token' => $requiresGuardianConsent ? Str::random(64) : null,
        ]);

        if ($requiresGuardianConsent) {
            $user->syncRoles(['minor_pending_consent']);

            try {
                Notification::send(
                    Notification::route('mail', $data['guardian_email']),
                    new GuardianConsentRequested($user)
                );
            } catch (\Throwable $exception) {
                Log::warning('Guardian consent notification could not be sent after social signup.', [
                    'user_id' => $user->id,
                    'guardian_email' => $data['guardian_email'],
                    'exception' => $exception->getMessage(),
                ]);
            }

            return redirect()->route('guardian-consent.pending');
        }

        $user->syncRoles(['player']);

        return redirect()->route('auth.dashboard')->with('success', 'Profil vervollstaendigt.');
    }
}
