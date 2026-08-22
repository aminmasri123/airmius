<?php

namespace App\Http\Middleware;

use App\Support\GuardianConsentState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGuardianConsentResolved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            GuardianConsentState::sync($user);
        }

        if (! $user || ! $user->hasRole('minor_pending_consent')) {
            return $next($request);
        }

        if ($this->isAllowedRoute($request)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            abort(403, __('guardian.validation.consent_required'));
        }

        return redirect()->route('guardian-consent.pending');
    }

    private function isAllowedRoute(Request $request): bool
    {
        return $request->routeIs(
            'guardian-consent.pending',
            'guardian-consent.resend',
            'guardian-access.*',
            'api.v1.auth.logout',
            'api.v1.me.show',
            'api.v1.me.language',
            'api.v1.me.email.verification.send',
            'api.v1.me.two-factor.*',
            'api.v1.me.password.update',
            'api.v1.me.sessions.*',
            'api.v1.guardian.consent.*',
            'api.v1.privacy.*',
            'api.v1.account.*',
            'logout',
            'verification.*',
            'password.*',
            'user.language.update',
        );
    }
}
