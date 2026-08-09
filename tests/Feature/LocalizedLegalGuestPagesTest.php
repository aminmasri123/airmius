<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\LegalContentLocalizer;
use App\Support\GovernanceAssuranceRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LocalizedLegalGuestPagesTest extends TestCase
{
    use RefreshDatabase;

    private const ROUTES = [
        'legal.imprint',
        'policy.show',
        'legal.account-deletion',
        'legal.data-erasure',
        'terms.show',
        'legal.community',
        'legal.minors',
        'legal.cookies',
        'legal.withdrawal',
        'legal.reporting',
    ];

    public function test_all_guest_legal_pages_are_complete_in_every_supported_locale(): void
    {
        $german = [];

        foreach (self::ROUTES as $routeName) {
            $response = $this->get(route($routeName, ['locale' => 'de']));
            $response->assertOk()
                ->assertHeader('Content-Language', 'de')
                ->assertHeader('X-Airmius-Text-Direction', 'ltr');

            $props = $response->viewData('page')['props'];
            $this->assertLegalContract($props, 'de', 'ltr');
            $german[$routeName] = $props;
        }

        foreach (['en' => 'ltr', 'fr' => 'ltr', 'ar' => 'rtl'] as $locale => $direction) {
            foreach (self::ROUTES as $routeName) {
                $response = $this->get(route($routeName, ['locale' => $locale]));
                $response->assertOk()
                    ->assertHeader('Content-Language', $locale)
                    ->assertHeader('X-Airmius-Text-Direction', $direction);

                $props = $response->viewData('page')['props'];
                $this->assertLegalContract($props, $locale, $direction);
                $this->assertSame(count($german[$routeName]['sections']), count($props['sections']));
                $this->assertContentIntegrity($german[$routeName], $props, $locale);
                $this->assertNotSame($german[$routeName]['title'], $props['title'], "{$routeName} title stayed German in {$locale}.");
                $this->assertNotSame($german[$routeName]['sections'], $props['sections'], "{$routeName} body stayed German in {$locale}.");
                if (is_string($props['action']['href'] ?? null)) {
                    $this->assertStringContainsString("locale={$locale}", $props['action']['href']);
                }

                if ($locale === 'ar') {
                    $this->assertMatchesRegularExpression('/\p{Arabic}/u', json_encode($props['sections'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                }
            }
        }
    }

    public function test_dynamic_legal_identity_is_preserved_while_surrounding_copy_is_localized(): void
    {
        $identity = [
            'billing_legal_name' => 'Airmius Legal Identity GmbH',
            'billing_company_street' => 'Sportweg 2030',
            'billing_company_postal_code' => '10115',
            'billing_company_city' => 'Berlin',
            'billing_company_country' => 'Deutschland',
            'billing_company_email' => 'identity@example.test',
            'billing_vat_id' => 'DE123456789',
            'billing_court' => 'Amtsgericht Berlin',
            'billing_registration_number' => 'HRB 203000',
            'billing_managing_director' => 'Ari Muster',
        ];
        foreach ($identity as $key => $value) {
            Setting::setValue($key, $value);
        }
        config([
            'legal.support_email' => 'support@example.test',
            'legal.privacy_email' => 'privacy@example.test',
            'legal.legal_email' => 'legal@example.test',
            'legal.phone' => '+49 30 203000',
            'legal.supervisory_authority' => 'Landesaufsicht Berlin',
        ]);

        foreach (['en', 'fr', 'ar'] as $locale) {
            $response = $this->get(route('policy.show', ['locale' => $locale]));
            $props = $response->viewData('page')['props'];
            $encoded = json_encode($props['sections'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            $this->assertTrue($props['translationComplete']);
            foreach ([
                'Airmius Legal Identity GmbH',
                'Sportweg 2030',
                '10115',
                'Berlin',
                'Deutschland',
                'identity@example.test',
                'privacy@example.test',
            ] as $protectedValue) {
                $this->assertStringContainsString($protectedValue, $encoded);
            }
            $this->assertStringNotContainsString('Verantwortlich für die Verarbeitung personenbezogener Daten ist:', $encoded);
            $this->assertStringNotContainsString('Datenschutzkontakt:', $encoded);
        }
    }

    public function test_unsupported_guest_locale_falls_back_to_the_german_source(): void
    {
        $response = $this->get(route('policy.show', ['locale' => 'es']));
        $props = $response->viewData('page')['props'];

        $response->assertOk()
            ->assertHeader('Content-Language', 'de')
            ->assertHeader('X-Airmius-Text-Direction', 'ltr');
        $this->assertSame('de', $props['contentLocale']);
        $this->assertSame('Datenschutzerklärung', $props['title']);
        $this->assertTrue($props['translationComplete']);
    }

    public function test_explicit_guest_locale_persists_for_the_next_navigation(): void
    {
        $this->get(route('policy.show', ['locale' => 'fr']))
            ->assertOk()
            ->assertSessionHas('locale', 'fr');

        $response = $this->get(route('terms.show'));
        $props = $response->viewData('page')['props'];

        $response->assertOk()
            ->assertHeader('Content-Language', 'fr');
        $this->assertSame('fr', $props['contentLocale']);
        $this->assertTrue($props['translationComplete']);
    }

    public function test_frontend_prevents_double_translation_and_preserves_locale_between_legal_pages(): void
    {
        $source = file_get_contents(resource_path('js/Pages/Legal/Show.vue'));

        $this->assertIsString($source);
        $this->assertStringContainsString('data-no-auto-translate', $source);
        $this->assertStringContainsString(':lang="contentLocale"', $source);
        $this->assertStringContainsString(':dir="textDirection"', $source);
        $this->assertStringContainsString("import LanguageDropdown from '@/Components/LanguageDropdown.vue'", $source);
        $this->assertStringContainsString('<LanguageDropdown />', $source);
        $this->assertStringContainsString('{ locale: props.contentLocale }', $source);
        $this->assertStringContainsString('{{ tx(item.labelKey) }}', $source);
        $this->assertStringContainsString(':description="seoDescription"', $source);
        $this->assertStringContainsString(':alternates="seoAlternates"', $source);
        $this->assertStringContainsString("hreflang: 'x-default'", $source);
        $this->assertStringContainsString('overflow-x-auto', $source);
        $this->assertStringContainsString("route().current(item.route) ? 'page'", $source);

        $footer = file_get_contents(resource_path('js/Components/Guest/Footer.vue'));
        $this->assertIsString($footer);
        $this->assertStringContainsString("page.props.locale || 'de'", $footer);
        $this->assertStringContainsString('locale: currentLocale.value', $footer);

        $seoHead = file_get_contents(resource_path('js/Components/Guest/SeoHead.vue'));
        $this->assertIsString($seoHead);
        $this->assertStringContainsString('rel="alternate"', $seoHead);
        $this->assertStringContainsString(':hreflang="alternate.hreflang"', $seoHead);

        foreach (['de', 'en', 'fr', 'ar'] as $locale) {
            $catalog = json_decode(
                file_get_contents(resource_path("js/lang/{$locale}.json")),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
            $this->assertNotEmpty(data_get($catalog, 'settings.delete_account.title'));
            $this->assertNotEmpty(data_get($catalog, 'data_erasure.meta_title'));
            if ($locale === 'ar') {
                $this->assertMatchesRegularExpression('/\p{Arabic}/u', $catalog['settings']['delete_account']['title']);
                $this->assertMatchesRegularExpression('/\p{Arabic}/u', $catalog['data_erasure']['meta_title']);
            }
        }
    }

    public function test_localized_legal_contract_is_part_of_governance_without_self_approval(): void
    {
        $registry = GovernanceAssuranceRegistry::definitions();

        $this->assertContains('app/Services/LegalContentLocalizer.php', $registry['artifacts']);
        $this->assertContains('tests/Feature/LocalizedLegalGuestPagesTest.php', $registry['artifacts']);
        $this->assertSame(LegalContentLocalizer::CONTRACT, $registry['legal_localization']['contract']);
        $this->assertSame(['de', 'en', 'fr', 'ar'], $registry['legal_localization']['locales']);
        $this->assertTrue($registry['legal_localization']['server_only_catalogs']);
        $this->assertTrue($registry['legal_localization']['safe_source_fallback']);
        $this->assertTrue($registry['legal_localization']['external_legal_and_native_review_required']);
        $this->assertFalse($registry['role_separation']['self_approval_allowed']);
        $this->assertFalse($registry['role_separation']['waiver_allowed']);
        $this->assertFalse($registry['evidence_policy']['local_evidence_is_authoritative']);
    }

    /** @param array<string, mixed> $props */
    private function assertLegalContract(array $props, string $locale, string $direction): void
    {
        $this->assertSame(LegalContentLocalizer::CONTRACT, $props['translationContract']);
        $this->assertSame('de', $props['sourceLocale']);
        $this->assertSame($locale, $props['contentLocale']);
        $this->assertSame($direction, $props['textDirection']);
        $this->assertTrue($props['translationComplete'], "Incomplete legal translation for {$locale}: {$props['title']}");
        $this->assertTrue($props['externalReviewRequired']);
        $this->assertNotEmpty($props['sections']);
        $this->assertNotEmpty($props['sections'][0]['body']);
    }

    /**
     * @param  array<string, mixed>  $german
     * @param  array<string, mixed>  $localized
     */
    private function assertContentIntegrity(array $german, array $localized, string $locale): void
    {
        $sourceTexts = $this->flattenContent($german);
        $targetTexts = $this->flattenContent($localized);
        $this->assertCount(count($sourceTexts), $targetTexts);

        foreach ($sourceTexts as $index => $source) {
            $target = $targetTexts[$index];

            preg_match_all('/\d+(?:[.,]\d+)*/u', $source, $sourceNumbers);
            preg_match_all('/\d+(?:[.,]\d+)*/u', $target, $targetNumbers);
            $this->assertSame($sourceNumbers[0], $targetNumbers[0], "Numbers changed in {$locale}: {$source}");

            preg_match_all('~(?<![\p{L}\p{N}-])/(?:[a-z0-9-]+/?)+~iu', $source, $sourcePaths);
            preg_match_all('~(?<![\p{L}\p{N}-])/(?:[a-z0-9-]+/?)+~iu', $target, $targetPaths);
            $this->assertSame($sourcePaths[0], $targetPaths[0], "Paths changed in {$locale}: {$source}");
            $this->assertSame(substr_count($source, '§'), substr_count($target, '§'), "Section signs changed in {$locale}: {$source}");

            foreach (['Airmius', 'Cloudflare', 'Gemini', 'Google', 'GraphHopper', 'Hostinger', 'IONOS', 'Mapbox', 'Microsoft', 'OpenAI', 'OpenStreetMap', 'OSRM', 'PayPal', 'R2', 'Stripe', 'openrouteservice'] as $brand) {
                if (str_contains($source, $brand)) {
                    $this->assertStringContainsString($brand, $target, "Brand changed in {$locale}: {$source}");
                }
            }

            if (mb_strlen($source) >= 50 && preg_match('/[A-Za-zÀ-ž]/u', $source)) {
                $this->assertNotSame(mb_strtolower($source), mb_strtolower($target), "Long legal copy stayed German in {$locale}: {$source}");
            }
        }
    }

    /** @param array<string, mixed> $props
     * @return array<int, string>
     */
    private function flattenContent(array $props): array
    {
        $texts = [$props['title']];
        if (is_string($props['note'] ?? null)) {
            $texts[] = $props['note'];
        }
        foreach ($props['sections'] as $section) {
            $texts[] = $section['title'];
            array_push($texts, ...$section['body']);
        }
        foreach (['label', 'authenticated_label'] as $key) {
            if (is_string($props['action'][$key] ?? null)) {
                $texts[] = $props['action'][$key];
            }
        }

        return $texts;
    }
}
