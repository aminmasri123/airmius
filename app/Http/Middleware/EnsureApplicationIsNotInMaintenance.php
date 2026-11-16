<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Support\MaintenanceSettings;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureApplicationIsNotInMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        // Machine-readable public responses have no session. Rendering the
        // Inertia maintenance page here would make Jetstream access a missing
        // session and turn maintenance into a 500 for crawlers and PWA clients.
        if ($request->routeIs('robots', 'sitemap', 'guest.blog.rss*', 'site.webmanifest')) {
            return $next($request);
        }

        if (! Setting::boolFor('maintenance_mode')) {
            return $next($request);
        }

        if ($this->canBypass($request) || $this->isAllowedRoute($request)) {
            return $next($request);
        }

        return Inertia::render('Maintenance', [
            ...MaintenanceSettings::data(),
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
            'user.language.update',
            'social-auth.*'
        );
    }
}
