<?php

namespace App\Http\Middleware;

use App\Support\AdminTwoFactor;
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
                abort(403, 'Bitte bestätige zuerst deine E-Mail-Adresse, bevor du den Adminbereich nutzt.');
            }

            return response('Bitte bestätige zuerst deine E-Mail-Adresse, bevor du den Adminbereich nutzt.', 403);
        }

        if (AdminTwoFactor::missingFor($request->user())) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => AdminTwoFactor::MESSAGE,
                    'code' => AdminTwoFactor::ERROR_CODE,
                ], 403);
            }

            return redirect()
                ->route('auth.settings', ['tab' => 'security'])
                ->with('error', AdminTwoFactor::MESSAGE);
        }

        if (! $request->isMethodSafe()
            && AdminTwoFactor::requiredFor($request->user())
            && ! AdminTwoFactor::freshWebStepUp($request->session()->get('auth.password_confirmed_at'))
        ) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => AdminTwoFactor::STEP_UP_MESSAGE,
                    'code' => AdminTwoFactor::STEP_UP_ERROR_CODE,
                ], 403);
            }

            return redirect()
                ->route('password.confirm')
                ->with('error', AdminTwoFactor::STEP_UP_MESSAGE);
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
