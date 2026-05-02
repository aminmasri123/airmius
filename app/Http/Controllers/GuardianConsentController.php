<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\View\View;

class GuardianConsentController extends Controller
{
    public function show(string $token): View
    {
        $minor = $this->findMinorByToken($token);

        return view('guardian-consent.show', [
            'minor' => $minor,
            'token' => $token,
        ]);
    }

    public function approve(Request $request, string $token): RedirectResponse
    {
        $minor = $this->findMinorByToken($token);

        $request->validate([
            'guardian_confirmation' => ['accepted'],
        ], [
            'guardian_confirmation.accepted' => 'Bitte bestaetigen Sie, dass Sie erziehungsberechtigt sind.',
        ]);

        $minor->forceFill([
            'guardian_user_id' => $request->user()?->id,
            'guardian_consent_at' => now(),
            'guardian_consent_rejected_at' => null,
            'guardian_consent_token' => null,
        ])->save();

        if ($minor->hasRole('minor_pending_consent')) {
            $minor->removeRole('minor_pending_consent');
        }

        if (! $minor->hasRole('minor_player')) {
            $minor->assignRole('minor_player');
        }

        if ($request->user() && ! $request->user()->hasAnyRole(['guardian', 'parent'])) {
            $request->user()->assignRole('guardian');
        }

        return redirect()
            ->route('login')
            ->with('status', 'Die Registrierung wurde bestaetigt.');
    }

    public function reject(Request $request, string $token): RedirectResponse
    {
        $minor = $this->findMinorByToken($token);

        $minor->forceFill([
            'guardian_user_id' => $request->user()?->id,
            'guardian_consent_rejected_at' => now(),
            'guardian_consent_token' => null,
        ])->save();

        return redirect()
            ->route('login')
            ->with('status', 'Die Registrierung wurde abgelehnt.');
    }

    public function pending(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Auth/GuardianConsent/Pending', [
            'guardianEmail' => $user->guardian_email,
            'requestedAt' => $user->guardian_consent_requested_at,
            'rejectedAt' => $user->guardian_consent_rejected_at,
            'approvedAt' => $user->guardian_consent_at,
        ]);
    }

    private function findMinorByToken(string $token): User
    {
        return User::where('guardian_consent_token', $token)
            ->whereNull('guardian_consent_at')
            ->whereNull('guardian_consent_rejected_at')
            ->firstOrFail();
    }
}
