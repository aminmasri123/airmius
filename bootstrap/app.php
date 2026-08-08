<?php

use App\Http\Middleware\ApplySecurityHeaders;
use App\Http\Middleware\AttachApiContractHeaders;
use App\Http\Middleware\EnforceApiCachePolicy;
use App\Http\Middleware\EnsureAccountIsNotSuspended;
use App\Http\Middleware\EnsureApiCorsHeaders;
use App\Http\Middleware\EnsureApplicationIsNotInMaintenance;
use App\Http\Middleware\EnsureGuardianConsentResolved;
use App\Http\Middleware\EnsureProfileIsComplete;
use App\Http\Middleware\EstablishProcessingPurpose;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\HardenAdminArea;
use App\Http\Middleware\MeasureRequestPerformance;
use App\Http\Middleware\SetCurrentClub;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\StoreIntendedUrlFromQuery;
use App\Http\Middleware\TrackUserActivity;
use App\Http\Middleware\TranslateUserFacingResponseText;
use App\Support\Api\V1\ApiContract;
use App\Support\Api\V1\ApiErrorResponse;
use App\Support\PermissionDeniedMessage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(EnsureApiCorsHeaders::class);
        // This must wrap the CORS exception renderer as well, otherwise an
        // error response could bypass the versioned API cache policy.
        $middleware->prepend(EnforceApiCachePolicy::class);
        $middleware->prepend(MeasureRequestPerformance::class);
        $middleware->append(ApplySecurityHeaders::class);

        $middleware->statefulApi();

        // Mobile/API clients can send X-Locale, X-App-Locale, or Accept-Language.
        $middleware->api(prepend: [
            SetLocale::class,
            TranslateUserFacingResponseText::class,
        ]);
        $middleware->api(append: [
            AttachApiContractHeaders::class,
        ]);

        // Locale must run after the default web session middleware and before Inertia.
        $middleware->web(append: [
            SetLocale::class,
            TranslateUserFacingResponseText::class,
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
            'purpose' => EstablishProcessingPurpose::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            // The versioned mobile API authenticates with short-lived bearer
            // tokens and is also consumed by the Flutter web build. A browser
            // Origin must not turn the public token login into a stateful SPA
            // request that fails before AuthController can validate credentials.
            'api/v1/auth/login',
            'webhooks/stripe',
            'webhooks/paypal',
            'webhooks/commerce/stripe',
            'webhooks/commerce/paypal',
            'webhooks/outfit-subscriptions/paypal',
            'checkout/subscriptions/*',
            'team-join-requests/*/approve',
            'team-join-requests/*/decline',
            'teams/*/members/*',
            'clubs/*/members/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Exception responses are created after the middleware stack unwinds.
        // Apply the negotiated locale headers here as well so clients can
        // reliably render validation and authorization failures in LTR/RTL.
        $exceptions->respond(fn (Response $response) => SetLocale::applyResponseHeaders($response));

        $apiV1Request = fn (Request $request): bool => ApiContract::matches($request);

        $exceptions->render(function (AuthenticationException $exception, Request $request) use ($apiV1Request) {
            return $apiV1Request($request)
                ? ApiErrorResponse::unauthenticated($exception, $request)
                : null;
        });

        $exceptions->render(function (ValidationException $exception, Request $request) use ($apiV1Request) {
            return $apiV1Request($request)
                ? ApiErrorResponse::validation($exception, $request)
                : null;
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) use ($apiV1Request) {
            return $apiV1Request($request)
                ? ApiErrorResponse::modelNotFound($exception, $request)
                : null;
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) use ($apiV1Request) {
            return $apiV1Request($request)
                ? ApiErrorResponse::notFound($exception, $request)
                : null;
        });

        $exceptions->render(function (MethodNotAllowedHttpException $exception, Request $request) use ($apiV1Request) {
            return $apiV1Request($request)
                ? ApiErrorResponse::methodNotAllowed($exception, $request)
                : null;
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) use ($apiV1Request) {
            return $apiV1Request($request)
                ? ApiErrorResponse::http($exception, $request)
                : null;
        });

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
                    'message' => __('csrf_session_expired'),
                ], 419);
            }

            return null;
        });

        $upgradePayload = function (?string $message = null): array {
            return [
                'status' => 403,
                'title' => PermissionDeniedMessage::TITLE,
                'message' => PermissionDeniedMessage::normalize($message),
                'upgradeUrl' => route('guest.pricing'),
            ];
        };

        $exceptions->render(function (AuthorizationException $exception, Request $request) use ($apiV1Request, $upgradePayload) {
            if ($apiV1Request($request)) {
                return ApiErrorResponse::forbidden($exception, $request);
            }

            if ($request->expectsJson()) {
                return null;
            }

            $rawMessage = trim((string) $exception->getMessage());
            $message = PermissionDeniedMessage::normalize($rawMessage);
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
            $message = PermissionDeniedMessage::normalize($rawMessage);
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

        $exceptions->render(function (Throwable $exception, Request $request) use ($apiV1Request) {
            return $apiV1Request($request)
                ? ApiErrorResponse::serverError($exception, $request)
                : null;
        });
    })->create();
