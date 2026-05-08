<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\GuardianConsentRequested;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
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

        $this->approveMinor($request, $minor);

        return redirect()
            ->route('login')
            ->with('status', 'Die Registrierung wurde bestaetigt.');
    }

    public function approveDirect(Request $request, string $token): RedirectResponse
    {
        $minor = $this->findMinorByToken($token);

        $this->approveMinor($request, $minor);

        return redirect()
            ->route('login')
            ->with('status', 'Die Registrierung wurde bestaetigt.');
    }

    public function reject(Request $request, string $token): RedirectResponse
    {
        $minor = $this->findMinorByToken($token);

        $this->rejectMinor($request, $minor);

        return redirect()
            ->route('login')
            ->with('status', 'Die Registrierung wurde abgelehnt.');
    }

    public function rejectDirect(Request $request, string $token): RedirectResponse
    {
        $minor = $this->findMinorByToken($token);

        $this->rejectMinor($request, $minor);

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
            'resendAvailableIn' => $user->guardian_consent_requested_at
                ? $this->resendAvailableIn($user)
                : 0,
        ]);
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->hasRole('minor_pending_consent'), 403);
        abort_if($user->guardian_consent_at, 422, 'Die Zustimmung wurde bereits erteilt.');
        abort_if(empty($user->guardian_email), 422, 'Es ist keine E-Mail eines Erziehungsberechtigten hinterlegt.');

        $availableIn = $this->resendAvailableIn($user);

        if ($availableIn > 0) {
            return back()->withErrors([
                'resend' => "Bitte warte noch {$availableIn} Sekunden, bevor du die E-Mail erneut sendest.",
            ]);
        }

        $user->forceFill([
            'guardian_consent_requested_at' => now(),
            'guardian_consent_rejected_at' => null,
            'guardian_consent_token' => $user->guardian_consent_token ?: Str::random(64),
        ])->save();

        try {
            Notification::send(
                Notification::route('mail', $user->guardian_email),
                new GuardianConsentRequested($user)
            );
        } catch (\Throwable $exception) {
            Log::warning('Guardian consent notification could not be resent.', [
                'user_id' => $user->id,
                'guardian_email' => $user->guardian_email,
                'exception' => $exception->getMessage(),
            ]);

            return back()->withErrors([
                'resend' => 'Die E-Mail konnte gerade nicht gesendet werden. Bitte versuche es spaeter erneut.',
            ]);
        }

        return back()->with('success', 'Die E-Mail wurde erneut gesendet.');
    }

    private function findMinorByToken(string $token): User
    {
        return User::where('guardian_consent_token', $token)
            ->whereNull('guardian_consent_at')
            ->whereNull('guardian_consent_rejected_at')
            ->firstOrFail();
    }

    private function resendAvailableIn(User $user): int
    {
        if (! $user->guardian_consent_requested_at) {
            return 0;
        }

        return (int) max(0, ceil(60 - $user->guardian_consent_requested_at->diffInSeconds(now())));
    }

    private function approveMinor(Request $request, User $minor): void
    {
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
    }

    private function rejectMinor(Request $request, User $minor): void
    {
        $minor->forceFill([
            'guardian_user_id' => $request->user()?->id,
            'guardian_consent_rejected_at' => now(),
            'guardian_consent_token' => null,
        ])->save();
    }
}
