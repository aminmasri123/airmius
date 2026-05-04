<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\EnsureApplicationIsNotInMaintenance;
use App\Http\Middleware\EnsureAccountIsNotSuspended;
use App\Http\Middleware\EnsureGuardianConsentResolved;
use App\Http\Middleware\EnsureProfileIsComplete;
use App\Http\Middleware\SetCurrentClub;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackUserActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

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
        //
    })->create();
