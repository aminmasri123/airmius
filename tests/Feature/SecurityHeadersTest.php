<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_web_responses_include_security_headers(): void
    {
        $response = $this->get(route('welcome'));

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('X-Permitted-Cross-Domain-Policies', 'none');

        $this->assertStringContainsString("default-src 'self'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("object-src 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString("'unsafe-eval'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('geolocation=(self)', $response->headers->get('Permissions-Policy'));
        $this->assertFalse($response->headers->has('Strict-Transport-Security'));
    }

    public function test_secure_requests_include_hsts(): void
    {
        $response = $this
            ->withHeader('X-Forwarded-Proto', 'https')
            ->get(route('welcome'));

        $response->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
    }

    public function test_local_vite_dev_server_is_allowed_by_csp(): void
    {
        $originalEnvironment = app()->environment();
        $hotFile = public_path('hot');
        $hotFileExisted = is_file($hotFile);
        $originalHotFile = $hotFileExisted ? file_get_contents($hotFile) : null;

        app()->detectEnvironment(fn () => 'local');
        file_put_contents($hotFile, 'http://127.0.0.1:5173');

        try {
            $response = $this->get(route('welcome'));

            $this->assertStringContainsString(
                'script-src',
                $response->headers->get('Content-Security-Policy')
            );
            $this->assertStringContainsString(
                'http://127.0.0.1:5173',
                $response->headers->get('Content-Security-Policy')
            );
            $this->assertStringContainsString(
                "'unsafe-eval'",
                $response->headers->get('Content-Security-Policy')
            );
            $this->assertMatchesRegularExpression(
                '/font-src[^;]*http:\/\/127\.0\.0\.1:5173/',
                $response->headers->get('Content-Security-Policy')
            );
        } finally {
            app()->detectEnvironment(fn () => $originalEnvironment);

            if ($hotFileExisted) {
                file_put_contents($hotFile, $originalHotFile);
            } else {
                unlink($hotFile);
            }
        }
    }

    public function test_api_responses_include_security_headers_without_losing_cors(): void
    {
        $response = $this
            ->withHeader('Origin', 'http://localhost')
            ->withHeader('Accept', 'application/json')
            ->getJson('/api/v1/meta');

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost');
    }
}
