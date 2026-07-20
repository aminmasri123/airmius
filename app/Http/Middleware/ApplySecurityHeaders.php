<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplySecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('airmius_security.headers.enabled', true)) {
            return $response;
        }

        $this->setHeaderIfMissing($response, 'X-Content-Type-Options', 'nosniff');
        $this->setHeaderIfMissing($response, 'X-Frame-Options', 'DENY');
        $this->setHeaderIfMissing($response, 'Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->setHeaderIfMissing($response, 'X-Permitted-Cross-Domain-Policies', 'none');
        $this->setHeaderIfMissing($response, 'Permissions-Policy', $this->permissionsPolicy());

        if (config('airmius_security.headers.csp_enabled', true)) {
            $this->setHeaderIfMissing($response, 'Content-Security-Policy', $this->contentSecurityPolicy());
        }

        if ($this->shouldSendHsts($request) && config('airmius_security.headers.hsts_enabled', true)) {
            $this->setHeaderIfMissing($response, 'Strict-Transport-Security', $this->hsts());
        }

        return $response;
    }

    private function setHeaderIfMissing(Response $response, string $name, string $value): void
    {
        if (! $response->headers->has($name)) {
            $response->headers->set($name, $value);
        }
    }

    private function contentSecurityPolicy(): string
    {
        $scriptSources = ["'self'", "'unsafe-inline'", "'unsafe-eval'"];
        $styleSources = ["'self'", "'unsafe-inline'", 'https://fonts.bunny.net'];
        $fontSources = ["'self'", 'data:', 'https://fonts.bunny.net'];
        $imageSources = ["'self'", 'data:', 'blob:', 'https:'];
        $mediaSources = ["'self'", 'data:', 'blob:', 'https:'];

        if ($viteDevServerOrigin = $this->viteDevServerOrigin()) {
            $scriptSources[] = $viteDevServerOrigin;
            $styleSources[] = $viteDevServerOrigin;
            $fontSources[] = $viteDevServerOrigin;
            $imageSources[] = $viteDevServerOrigin;
            $mediaSources[] = $viteDevServerOrigin;
        }

        return collect([
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            'script-src '.implode(' ', $scriptSources),
            'style-src '.implode(' ', $styleSources),
            'font-src '.implode(' ', $fontSources),
            'img-src '.implode(' ', $imageSources),
            'media-src '.implode(' ', $mediaSources),
            "connect-src 'self' http: https: ws: wss:",
            "worker-src 'self' blob:",
            "manifest-src 'self'",
        ])->implode('; ');
    }

    private function viteDevServerOrigin(): ?string
    {
        if (! app()->environment('local')) {
            return null;
        }

        $hotFile = public_path('hot');

        if (! is_file($hotFile) || ! is_readable($hotFile)) {
            return null;
        }

        $url = trim((string) file_get_contents($hotFile));
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        if (! in_array($parts['scheme'], ['http', 'https'], true)) {
            return null;
        }

        $origin = $parts['scheme'].'://'.$parts['host'];

        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $origin;
    }

    private function permissionsPolicy(): string
    {
        return collect([
            'accelerometer=()',
            'camera=()',
            'display-capture=()',
            'encrypted-media=()',
            'fullscreen=(self)',
            'geolocation=(self)',
            'gyroscope=()',
            'magnetometer=()',
            'microphone=()',
            'payment=(self)',
            'usb=()',
        ])->implode(', ');
    }

    private function hsts(): string
    {
        $parts = [
            'max-age='.(int) config('airmius_security.headers.hsts_max_age', 31536000),
        ];

        if (config('airmius_security.headers.hsts_include_subdomains', true)) {
            $parts[] = 'includeSubDomains';
        }

        if (config('airmius_security.headers.hsts_preload', true)) {
            $parts[] = 'preload';
        }

        return implode('; ', $parts);
    }

    private function shouldSendHsts(Request $request): bool
    {
        return $request->isSecure()
            || strtolower((string) $request->headers->get('X-Forwarded-Proto')) === 'https';
    }
}
