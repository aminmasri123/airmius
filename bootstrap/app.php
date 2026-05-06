<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\EnsureApplicationIsNotInMaintenance;
use App\Http\Middleware\EnsureAccountIsNotSuspended;
use App\Http\Middleware\EnsureGuardianConsentResolved;
use App\Http\Middleware\EnsureProfileIsComplete;
use App\Http\Middleware\SetCurrentClub;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackUserActivity;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
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

        // 🔥 WICHTIG: zuerst ausführen
        $middleware->web(prepend: [
            SetLocale::class,
        ]);

        // normale Middleware danach
        $middleware->web(append: [
            HandleInertiaRequests::class,
            EnsureApplicationIsNotInMaintenance::class,
            EnsureAccountIsNotSuspended::class,
            EnsureProfileIsComplete::class,
            EnsureGuardianConsentResolved::class,
            TrackUserActivity::class,
            AddLinkHeadersForPreloadedAssets::class,

        ]);

        $middleware->alias([
            'club' => SetCurrentClub::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/stripe',
            'webhooks/paypal',
            'webhooks/commerce/stripe',
            'webhooks/commerce/paypal',
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {
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
