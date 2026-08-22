<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AppNotification;
use App\Support\GuardianConsentNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class GuardianConsentController extends Controller
{
    public function show(string $token): View
    {
        $minor = $this->findMinorByToken($token);

        return view('guardian-consent.show', [
            'minor' => $minor,
            'token' => $token,
            'consentVersion' => $minor->guardian_consent_version ?: config('guardian.consent_version'),
        ]);
    }

    public function approve(Request $request, string $token): RedirectResponse
    {
        $minor = $this->findMinorByToken($token);

        $request->validate([
            'guardian_confirmation' => ['accepted'],
        ], [
            'guardian_confirmation.accepted' => __('guardian.validation.confirmation_required'),
        ]);

        $this->approveMinor($request, $minor);

        return $this->redirectAfterDecision($request, __('guardian.responses.registration_approved'));
    }

    public function approveDirect(Request $request, string $token): RedirectResponse
    {
        $this->findMinorByToken($token);

        return redirect()->route('guardian-consent.show', $token);
    }

    public function reject(Request $request, string $token): RedirectResponse
    {
        $minor = $this->findMinorByToken($token);

        $this->rejectMinor($request, $minor);

        return $this->redirectAfterDecision($request, __('guardian.responses.registration_rejected'));
    }

    public function rejectDirect(Request $request, string $token): RedirectResponse
    {
        $this->findMinorByToken($token);

        return redirect()->route('guardian-consent.show', $token);
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
        abort_if($user->guardian_consent_at, 422, __('guardian.validation.already_approved'));
        abort_if(empty($user->guardian_email), 422, __('guardian.validation.guardian_email_missing'));

        $availableIn = $this->resendAvailableIn($user);

        if ($availableIn > 0) {
            return back()->withErrors([
                'resend' => __('guardian.validation.resend_wait', ['seconds' => $availableIn]),
            ]);
        }

        $user->forceFill([
            'guardian_consent_requested_at' => now(),
            'guardian_consent_rejected_at' => null,
            'guardian_consent_token' => $user->guardian_consent_token ?: Str::random(64),
            'guardian_consent_version' => config('guardian.consent_version'),
        ])->save();

        GuardianConsentNotifier::send($user, $user->guardian_email);

        return back()->with('success', __('guardian.responses.email_resent'));
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
            'guardian_consent_version' => $minor->guardian_consent_version ?: config('guardian.consent_version'),
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

        AppNotification::sendLocalized(
            $minor,
            'guardian.consent_approved',
            'guardian.notifications.approved_title',
            'guardian.notifications.approved_body',
            data: [
                'minor_id' => $minor->id,
                'guardian_user_id' => $minor->guardian_user_id,
                'url' => route('auth.dashboard'),
            ],
        );
    }

    private function rejectMinor(Request $request, User $minor): void
    {
        $minor->forceFill([
            'guardian_user_id' => $request->user()?->id,
            'guardian_consent_rejected_at' => now(),
            'guardian_consent_token' => null,
        ])->save();

        AppNotification::sendLocalized(
            $minor,
            'guardian.consent_rejected',
            'guardian.notifications.rejected_title',
            'guardian.notifications.rejected_body',
            data: [
                'minor_id' => $minor->id,
                'guardian_user_id' => $minor->guardian_user_id,
                'url' => route('guardian-consent.pending'),
            ],
        );
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
