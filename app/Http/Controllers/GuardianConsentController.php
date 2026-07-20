<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AppNotification;
use App\Support\GuardianConsentNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'guardian_confirmation.accepted' => 'Bitte bestätigen Sie, dass Sie erziehungsberechtigt sind.',
        ]);

        $this->approveMinor($request, $minor);

        return $this->redirectAfterDecision($request, 'Die Registrierung wurde bestätigt.');
    }

    public function approveDirect(Request $request, string $token): RedirectResponse
    {
        $minor = $this->findMinorByToken($token);

        $this->approveMinor($request, $minor);

        return $this->redirectAfterDecision($request, 'Die Registrierung wurde bestätigt.');
    }

    public function reject(Request $request, string $token): RedirectResponse
    {
        $minor = $this->findMinorByToken($token);

        $this->rejectMinor($request, $minor);

        return $this->redirectAfterDecision($request, 'Die Registrierung wurde abgelehnt.');
    }

    public function rejectDirect(Request $request, string $token): RedirectResponse
    {
        $minor = $this->findMinorByToken($token);

        $this->rejectMinor($request, $minor);

        return $this->redirectAfterDecision($request, 'Die Registrierung wurde abgelehnt.');
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

        GuardianConsentNotifier::send($user, $user->guardian_email);

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

        AppNotification::send($minor, 'guardian.consent_approved', [
            'title' => 'Zustimmung erteilt',
            'body' => 'Dein Airmius-Konto wurde von einem Erziehungsberechtigten freigegeben.',
            'minor_id' => $minor->id,
            'guardian_user_id' => $minor->guardian_user_id,
            'url' => route('auth.dashboard'),
        ]);
    }

    private function rejectMinor(Request $request, User $minor): void
    {
        $minor->forceFill([
            'guardian_user_id' => $request->user()?->id,
            'guardian_consent_rejected_at' => now(),
            'guardian_consent_token' => null,
        ])->save();

        AppNotification::send($minor, 'guardian.consent_rejected', [
            'title' => 'Zustimmung abgelehnt',
            'body' => 'Die Freigabe deines Airmius-Kontos wurde abgelehnt. Du kannst eine erneute Anfrage ausloesen.',
            'minor_id' => $minor->id,
            'guardian_user_id' => $minor->guardian_user_id,
            'url' => route('guardian-consent.pending'),
        ]);
    }

    private function redirectAfterDecision(Request $request, string $message): RedirectResponse
    {
        $route = $request->user() ? 'auth.dashboard' : 'login';

        return redirect()
            ->route($route)
            ->with('status', $message)
            ->with('success', $message);
    }
}
