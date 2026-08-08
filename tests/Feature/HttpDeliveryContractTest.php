<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HttpDeliveryContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_api_meta_uses_a_locale_aware_etag(): void
    {
        $response = $this
            ->withHeaders([
                'Accept' => 'application/json',
                'Accept-Language' => 'fr-FR,fr;q=0.9',
            ])
            ->get('/api/v1/meta')
            ->assertOk();

        $etag = $response->headers->get('ETag');

        $this->assertNotNull($etag);
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=300', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('Accept-Language', (string) $response->headers->get('Vary'));

        $this
            ->withHeaders([
                'Accept' => 'application/json',
                'Accept-Language' => 'fr-FR,fr;q=0.9',
                'If-None-Match' => $etag,
            ])
            ->get('/api/v1/meta')
            ->assertNotModified()
            ->assertHeader('ETag', $etag);
    }

    public function test_cache_and_locale_headers_are_exposed_to_allowed_browser_clients(): void
    {
        $response = $this
            ->withHeaders([
                'Accept' => 'application/json',
                'Origin' => 'http://localhost',
            ])
            ->get('/api/v1/meta')
            ->assertOk();

        $this->assertStringContainsString('ETag', (string) $response->headers->get('Access-Control-Expose-Headers'));
        $this->assertStringContainsString('X-Request-Id', (string) $response->headers->get('Access-Control-Expose-Headers'));
        $this->assertStringContainsString('X-Locale', (string) $response->headers->get('Access-Control-Allow-Headers'));
    }

    public function test_authorization_disables_public_meta_caching(): void
    {
        $response = $this
            ->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Bearer intentionally-invalid-for-cache-policy',
            ])
            ->get('/api/v1/meta')
            ->assertOk();

        $this->assertPrivateNoStore($response->headers->get('Cache-Control'));
    }

    public function test_cookies_disable_public_meta_caching(): void
    {
        $response = $this
            ->withCredentials()
            ->withCookie('airmius-test-session', 'anonymous-session')
            ->getJson('/api/v1/meta')
            ->assertOk();

        $this->assertPrivateNoStore($response->headers->get('Cache-Control'));
    }

    public function test_authenticated_api_responses_are_never_shared_or_stored(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this
            ->getJson('/api/v1/sports')
            ->assertOk()
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('Expires', '0');

        $this->assertPrivateNoStore($response->headers->get('Cache-Control'));
    }

    public function test_api_error_responses_cannot_bypass_private_cache_policy(): void
    {
        $response = $this
            ->getJson('/api/v1/route-that-does-not-exist')
            ->assertStatus(405);

        $this->assertPrivateNoStore($response->headers->get('Cache-Control'));
    }

    public function test_apache_delivery_contract_is_scoped_to_hashed_assets(): void
    {
        $configuration = file_get_contents(public_path('.htaccess'));

        $this->assertIsString($configuration);
        $this->assertStringContainsString('RewriteRule ^build/assets/ - [E=AIRMIUS_IMMUTABLE_ASSET:1]', $configuration);
        $this->assertStringContainsString('public, max-age=31536000, immutable', $configuration);
        $this->assertStringContainsString('RewriteRule ^build/manifest\\.json$ - [E=AIRMIUS_BUILD_MANIFEST:1]', $configuration);
        $this->assertStringContainsString('no-cache, must-revalidate', $configuration);
        $this->assertStringContainsString('BROTLI_COMPRESS', $configuration);
        $this->assertStringContainsString('DEFLATE', $configuration);
        $this->assertStringNotContainsString('RewriteRule ^build/ - [E=AIRMIUS_IMMUTABLE_ASSET:1]', $configuration);
    }

    private function assertPrivateNoStore(?string $cacheControl): void
    {
        $this->assertNotNull($cacheControl);

        foreach (['private', 'no-store', 'no-cache', 'must-revalidate', 'max-age=0'] as $directive) {
            $this->assertStringContainsString($directive, $cacheControl);
        }
    }
}
