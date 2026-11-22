<?php

namespace App\Http\Middleware;

use App\Support\AccountAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsNotSuspended
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->privacy_status === 'anonymized') {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        if (! AccountAccess::isSuspended($user)) {
            return $next($request);
        }

        if ($request->is('logout') || $request->is('legal*')) {
            return $next($request);
        }

        return Inertia::render('Auth/Suspended', [
            'reason' => $user->suspension_reason,
            'suspended_until' => $user->suspended_until?->toDateTimeString(),
        ])->toResponse($request)->setStatusCode(423);
    }
}
