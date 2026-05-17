<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class HardenAdminArea
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->email_verified_at) {
            if ($request->expectsJson()) {
                abort(403, 'Bitte bestaetige zuerst deine E-Mail-Adresse, bevor du den Adminbereich nutzt.');
            }

            return response('Bitte bestaetige zuerst deine E-Mail-Adresse, bevor du den Adminbereich nutzt.', 403);
        }

        $response = $next($request);

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        if (! $request->isMethodSafe()) {
            Log::info('admin.action', [
                'user_id' => $request->user()?->id,
                'method' => $request->method(),
                'path' => $request->path(),
                'route' => $request->route()?->getName(),
                'status' => $response->getStatusCode(),
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
            ]);
        }

        return $response;
    }
}
