<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnsureApiCorsHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            if ($this->isPreflight($request)) {
                $response = response('', 204);
            } else {
                $response = $next($request);
            }
        } catch (Throwable $error) {
            $response = app(ExceptionHandler::class)->render($request, $error);
        }

        return $this->withCorsHeaders($request, $response);
    }

    private function isPreflight(Request $request): bool
    {
        return $request->isMethod('OPTIONS') && $request->headers->has('Access-Control-Request-Method');
    }

    private function withCorsHeaders(Request $request, Response $response): Response
    {
        $origin = $request->headers->get('Origin');

        if ($origin && $this->isAllowedOrigin($origin)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Vary', 'Origin', false);
        }

        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Authorization, Content-Type, Accept, X-Requested-With, X-Airmius-Locale, X-App-Locale, X-CSRF-TOKEN, X-XSRF-TOKEN');
        $response->headers->set('Access-Control-Expose-Headers', 'Authorization, Content-Type');
        $response->headers->set('Access-Control-Max-Age', '86400');

        return $response;
    }

    private function isAllowedOrigin(string $origin): bool
    {
        $host = parse_url($origin, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower(trim($host, '[]'));

        if (in_array($host, [
            'airmius.com',
            'www.airmius.com',
            'app.airmius.com',
            'localhost',
            '127.0.0.1',
            '0.0.0.0',
            '::1',
        ], true)) {
            return true;
        }

        if (str_ends_with($host, '.local')) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        }

        return false;
    }
}
