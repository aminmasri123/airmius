<?php

namespace Tests\Feature;

use App\Support\LocalizedPublicUrl;
use App\Support\SupportedLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LocalizedGuestSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_home_does_not_disclose_runtime_versions(): void
    {
        $this->get(route('welcome'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->missing('laravelVersion')
                ->missing('phpVersion'));

        $source = File::get(resource_path('js/Pages/Welcome.vue'));
        $this->assertStringNotContainsString('laravelVersion', $source);
        $this->assertStringNotContainsString('phpVersion', $source);
    }

    public function test_guest_initial_html_exposes_localized_metadata_and_reciprocal_alternates(): void
    {
        $expectations = [
            'de' => ['Airmius Sport Plattform', 'de_DE'],
            'en' => ['Airmius Sports Platform', 'en_US'],
            'fr' => ['Plateforme sportive Airmius', 'fr_FR'],
            'ar' => ['منصة Airmius الرياضية', 'ar_AR'],
        ];

        foreach ($expectations as $locale => [$title, $openGraphLocale]) {
            $parameters = $locale === SupportedLocale::DEFAULT ? [] : ['locale' => $locale];
            $canonical = route('welcome', $parameters);
            $response = $this->get($canonical);

            $response->assertOk()
                ->assertHeader('Content-Language', $locale)
                ->assertSee('lang="'.$locale.'"', false)
                ->assertSee('<title inertia>'.$title.'</title>', false)
                ->assertSee('<link rel="canonical" href="'.$canonical.'"', false)
                ->assertSee('<meta property="og:locale" content="'.$openGraphLocale.'"', false);

            foreach (LocalizedPublicUrl::alternates(route('welcome')) as $alternate) {
                $response->assertSee(
                    'rel="alternate" hreflang="'.$alternate['hreflang'].'" href="'.$alternate['href'].'"',
                    false,
                );
            }

            $vary = (string) $response->headers->get('Vary');
            foreach (['Accept-Language', 'X-Locale', 'X-App-Locale', 'X-Airmius-Locale'] as $header) {
                $this->assertStringContainsString($header, $vary);
            }
        }
    }

    public function test_guest_sitemap_lists_every_locale_with_xhtml_hreflang(): void
    {
        Cache::flush();

        $response = $this->get(route('sitemap'));

        $response->assertOk()
            ->assertSee('xmlns:xhtml="http://www.w3.org/1999/xhtml"', false);

        foreach (SupportedLocale::ALL as $locale) {
            $localized = LocalizedPublicUrl::forLocale(route('guest.marketplace'), $locale);
            $response
                ->assertSee('<loc>'.e($localized).'</loc>', false)
                ->assertSee('hreflang="'.$locale.'" href="'.e($localized).'"', false);
        }

        $response->assertSee(
            'hreflang="x-default" href="'.e(route('guest.marketplace')).'"',
            false,
        );

        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml);
        $this->assertLessThanOrEqual(
            LocalizedPublicUrl::SITEMAP_PROTOCOL_URL_LIMIT,
            LocalizedPublicUrl::sitemapMaxLocalizedUrls(),
        );
        $this->assertSame(48000, LocalizedPublicUrl::sitemapMaxLocalizedUrls());
    }

    public function test_localized_public_urls_preserve_canonical_filters_without_duplicate_locale_parameters(): void
    {
        $baseUrl = route('guest.marketplace', [
            'category' => 'training plan',
            'locale' => 'fr',
        ]);

        $localized = LocalizedPublicUrl::forLocale($baseUrl.'#products', 'ar');

        $this->assertSame(
            route('guest.marketplace', ['category' => 'training plan', 'locale' => 'ar']),
            $localized,
        );
        $this->assertSame(route('guest.marketplace', ['category' => 'training plan']), LocalizedPublicUrl::forLocale($localized, 'de'));
        $this->assertSame(1, substr_count($localized, 'locale='));
        $this->assertStringNotContainsString('#products', $localized);
    }

    public function test_server_guest_seo_catalogs_have_identical_keys_and_placeholders(): void
    {
        $catalogs = collect(SupportedLocale::ALL)->mapWithKeys(fn (string $locale): array => [
            $locale => Arr::dot(require lang_path("{$locale}/guest_seo.php")),
        ]);
        $source = $catalogs[SupportedLocale::DEFAULT];

        foreach ($catalogs as $locale => $catalog) {
            $this->assertSame(array_keys($source), array_keys($catalog), "SEO key mismatch for {$locale}.");

            foreach ($source as $key => $sourceValue) {
                preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', $sourceValue, $sourcePlaceholders);
                preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', $catalog[$key], $targetPlaceholders);
                sort($sourcePlaceholders[0]);
                sort($targetPlaceholders[0]);
                $this->assertSame($sourcePlaceholders[0], $targetPlaceholders[0], "SEO placeholder mismatch for {$locale}.{$key}.");
            }
        }
    }
}
