<?php

use App\Http\Middleware\EnsureAccountIsNotSuspended;
use App\Http\Middleware\EnsureApiCorsHeaders;
use App\Http\Middleware\EnsureApplicationIsNotInMaintenance;
use App\Http\Middleware\EnsureGuardianConsentResolved;
use App\Http\Middleware\EnsureProfileIsComplete;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\HardenAdminArea;
use App\Http\Middleware\SetCurrentClub;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\StoreIntendedUrlFromQuery;
use App\Http\Middleware\TrackUserActivity;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        // Mobile/API clients can send X-Locale, X-App-Locale, or Accept-Language.
        $middleware->api(prepend: [
            EnsureApiCorsHeaders::class,
            SetLocale::class,
        ]);

        // Locale must run after the default web session middleware and before Inertia.
        $middleware->web(append: [
            SetLocale::class,
            HandleInertiaRequests::class,
            EnsureApplicationIsNotInMaintenance::class,
            EnsureAccountIsNotSuspended::class,
            EnsureProfileIsComplete::class,
            EnsureGuardianConsentResolved::class,
            StoreIntendedUrlFromQuery::class,
            TrackUserActivity::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'club' => SetCurrentClub::class,
            'admin.harden' => HardenAdminArea::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/stripe',
            'webhooks/paypal',
            'webhooks/commerce/stripe',
            'webhooks/commerce/paypal',
            'webhooks/outfit-subscriptions/paypal',
            'checkout/subscriptions/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TokenMismatchException $exception, Request $request) {
            Log::warning('CSRF token mismatch', [
                'method' => $request->method(),
                'path' => $request->path(),
                'route' => $request->route()?->getName(),
                'user_id' => $request->user()?->id,
                'session_id' => $request->hasSession() ? $request->session()->getId() : null,
                'host' => $request->getHost(),
                'origin' => $request->headers->get('origin'),
                'referer' => $request->headers->get('referer'),
                'expects_json' => $request->expectsJson(),
                'ajax' => $request->ajax(),
                'has_session_cookie' => $request->cookies->has(config('session.cookie')),
                'has_xsrf_cookie' => $request->cookies->has('XSRF-TOKEN'),
                'has_csrf_header' => $request->headers->has('X-CSRF-TOKEN'),
                'has_xsrf_header' => $request->headers->has('X-XSRF-TOKEN'),
                'user_agent' => $request->userAgent(),
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => 'Deine Sitzung ist abgelaufen. Bitte lade die Seite neu und versuche es erneut.',
                ], 419);
            }

            return null;
        });

        $upgradePayload = function (?string $message = null): array {
            $fallback = 'Diese Funktion ist in deinem aktuellen Paket oder mit deiner aktuellen Rolle nicht freigeschaltet. Bitte fuehre ein Upgrade durch oder bitte deinen Verein/Admin um die passende Berechtigung.';

            return [
                'status' => 403,
                'title' => 'Upgrade oder Berechtigung erforderlich',
                'message' => $message ?: $fallback,
                'upgradeUrl' => route('guest.pricing'),
            ];
        };

        $exceptions->render(function (AuthorizationException $exception, Request $request) use ($upgradePayload) {
            if ($request->expectsJson()) {
                return null;
            }

            $rawMessage = trim((string) $exception->getMessage());
            $message = $rawMessage && $rawMessage !== 'This action is unauthorized.'
                ? $rawMessage
                : null;
            $payload = $upgradePayload($message);

            if (! $request->isMethod('get')) {
                return back()
                    ->withErrors(['authorization' => $payload['message']])
                    ->with('upgrade_required', $payload);
            }

            return Inertia::render('Errors/Forbidden', $payload)
                ->toResponse($request)
                ->setStatusCode(403);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) use ($upgradePayload) {
            if ($exception->getStatusCode() !== 403 || $request->expectsJson()) {
                return null;
            }

            $rawMessage = trim((string) $exception->getMessage());
            $message = $rawMessage && $rawMessage !== 'This action is unauthorized.'
                ? $rawMessage
                : null;
            $payload = $upgradePayload($message);

            if (! $request->isMethod('get')) {
                return back()
                    ->withErrors(['authorization' => $payload['message']])
                    ->with('upgrade_required', $payload);
            }

            return Inertia::render('Errors/Forbidden', $payload)
                ->toResponse($request)
                ->setStatusCode(403);
        });
    })->create();
