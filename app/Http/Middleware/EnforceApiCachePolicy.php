<?php

namespace App\Http\Middleware;

use App\Support\Api\V1\ApiContract;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceApiCachePolicy
{
    private const PUBLIC_REFERENCE_ROUTES = [
        'api.v1.meta',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! ApiContract::matches($request)) {
            return $response;
        }

        if ($this->canCachePublicReference($request, $response)) {
            return $this->cachePublicReference($request, $response);
        }

        return $this->protectPrivateResponse($response);
    }

    private function canCachePublicReference(Request $request, Response $response): bool
    {
        return $request->isMethodCacheable()
            && in_array($request->route()?->getName(), self::PUBLIC_REFERENCE_ROUTES, true)
            && $request->user() === null
            && ! $request->headers->has('Authorization')
            && ! $this->hasCookies($request)
            && ! $response->headers->has('Set-Cookie')
            && $response->getStatusCode() === Response::HTTP_OK
            && is_string($response->getContent());
    }

    private function hasCookies(Request $request): bool
    {
        return $request->cookies->all() !== []
            || trim((string) $request->server('HTTP_COOKIE')) !== '';
    }

    private function cachePublicReference(Request $request, Response $response): Response
    {
        $content = (string) $response->getContent();

        // A weak validator remains valid when Apache serves the same JSON with
        // Brotli or gzip content encoding.
        $response->setEtag(hash('sha256', $content), true);
        $response->headers->set(
            'Cache-Control',
            'public, max-age=300, stale-while-revalidate=60, must-revalidate'
        );
        $response->setVary(implode(', ', array_values(array_unique([
            ...$response->getVary(),
            'Accept-Language',
            'X-Locale',
            'X-App-Locale',
            'X-Airmius-Locale',
        ]))));
        $response->isNotModified($request);

        return $response;
    }

    private function protectPrivateResponse(Response $response): Response
    {
        $cacheControl = strtolower((string) $response->headers->get('Cache-Control'));

        // Preserve deliberate private download caching while denying storage
        // for every other versioned API response by default.
        if (str_contains($cacheControl, 'private')
            && preg_match('/(?:^|,)\s*max-age=[1-9][0-9]*/', $cacheControl) === 1
            && ! str_contains($cacheControl, 'no-store')) {
            return $response;
        }

        $response->headers->set(
            'Cache-Control',
            'private, no-store, no-cache, must-revalidate, max-age=0'
        );
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
