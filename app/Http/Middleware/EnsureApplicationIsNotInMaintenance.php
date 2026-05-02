<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureApplicationIsNotInMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Setting::boolFor('maintenance_mode')) {
            return $next($request);
        }

        if ($this->canBypass($request) || $this->isAllowedRoute($request)) {
            return $next($request);
        }

        return Inertia::render('Maintenance', [
            'title' => Setting::valueFor('maintenance_title', 'Airmius ist gerade im Wartemodus'),
            'message' => Setting::valueFor(
                'maintenance_message',
                'Wir verbessern gerade die Plattform. Bitte versuche es in Kürze erneut.'
            ),
            'canLogin' => ! $request->user(),
        ])->toResponse($request)->setStatusCode(503);
    }

    private function canBypass(Request $request): bool
    {
        $user = $request->user();

        return $user && (
            $user->can('system.manage')
            || $user->hasAnyRole(['super_admin', 'admin', 'system_admin'])
        );
    }

    private function isAllowedRoute(Request $request): bool
    {
        return $request->routeIs(
            'login',
            'login.store',
            'logout',
            'password.*',
            'verification.*',
            'two-factor.*',
            'user.language.update'
        );
    }
}
