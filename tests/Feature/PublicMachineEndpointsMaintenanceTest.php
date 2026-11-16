<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicMachineEndpointsMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_machine_endpoints_remain_stateless_during_maintenance(): void
    {
        Setting::setValue('maintenance_mode', true);

        foreach (['/robots.txt', '/sitemap.xml', '/blog/rss.xml', '/rss.xml', '/site.webmanifest'] as $path) {
            $response = $this->get($path);
            $response->assertOk()->assertHeaderMissing('Set-Cookie');
            $this->assertNotEmpty($response->headers->get('ETag'));
            $this->withHeader('If-None-Match', $response->headers->get('ETag'))
                ->get($path)->assertNotModified();
            $this->flushHeaders();
        }

        $this->get('/')->assertStatus(503)->assertSee('Maintenance');
    }

    public function test_root_feed_alias_uses_the_same_localized_public_feed(): void
    {
        foreach (['de', 'en', 'fr', 'ar'] as $locale) {
            $canonical = $this->get('/blog/rss.xml?locale='.$locale);
            $alias = $this->get('/rss.xml?locale='.$locale);
            $alias->assertOk()
                ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')
                ->assertHeader('Content-Language', $locale)
                ->assertHeaderMissing('Set-Cookie');
            $this->assertSame($canonical->getContent(), $alias->getContent());
        }
    }
}
