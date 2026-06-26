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

        return redirect()->route('guardian-consent.pending');
    }

    private function isAllowedRoute(Request $request): bool
    {
        return $request->routeIs(
            'guardian-consent.pending',
            'guardian-consent.resend',
            'guardian-access.*',
            'logout',
            'verification.*',
            'password.*',
            'user.language.update',
        );
    }
}
