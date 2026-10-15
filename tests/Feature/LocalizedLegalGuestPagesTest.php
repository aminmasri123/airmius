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
        'legal.club-deletion',
        'legal.data-erasure',
        'terms.show',
        'legal.community',
        'legal.minors',
        'legal.cookies',
        'legal.withdrawal',
        'legal.reporting',
    ];

    public function test_club_deletion_guide_is_complete_in_german_and_english(): void
    {
        foreach (['de', 'en'] as $locale) {
            $response = $this->get(route('legal.club-deletion', ['locale' => $locale]));
            $response->assertOk()->assertHeader('Content-Language', $locale);
            $props = $response->viewData('page')['props'];
            $this->assertSame($locale, $props['contentLocale']);
            $this->assertTrue($props['translationComplete']);
            $this->assertCount(7, $props['sections']);
        }
    }

    public function test_club_deletion_guide_distinguishes_platform_records_from_club_data_in_every_language(): void
    {
        foreach (['de', 'en', 'fr', 'ar'] as $locale) {
            $sections = $this->get(route('legal.club-deletion', ['locale' => $locale, 'format' => 'json']))
                ->assertOk()
                ->json('sections');
            $body = implode("\n", array_merge(...array_column($sections, 'body')));

            $this->assertStringContainsString('Airmius', $body);
            $this->assertStringContainsString(match ($locale) {
                'de' => 'nicht unbegrenzt',
                'en' => 'not be retained indefinitely',
                'fr' => 'conservation illimitée',
                'ar' => 'إلى أجل غير مسمى',
            }, $body);
        }
    }

    public function test_public_account_deletion_action_says_sign_in_in_every_language(): void
    {
        foreach ([
            'de' => 'Anmelden und Konto löschen',
            'en' => 'Sign in and delete account',
            'fr' => 'Se connecter et supprimer le compte',
            'ar' => 'تسجيل الدخول وحذف الحساب',
        ] as $locale => $label) {
            $response = $this->get(route('legal.account-deletion', ['locale' => $locale]));
            $response->assertOk();
            $this->assertSame($label, $response->viewData('page')['props']['action']['label']);
            $this->get(route('legal.account-deletion', ['locale' => $locale, 'format' => 'json']))
                ->assertOk()
                ->assertJsonPath('action.label', $label);
        }
    }

    public function test_account_deletion_guide_distinguishes_self_service_from_manual_requests(): void
    {
        foreach ([
            'de' => 'Ohne Kontozugriff kannst du ein gesondertes Ersuchen',
            'en' => 'If you cannot access your account, you can make a separate request',
            'fr' => 'Si vous ne pouvez plus accéder au compte, vous pouvez adresser une demande distincte',
            'ar' => 'إذا تعذر عليك الوصول إلى حسابك، يمكنك تقديم طلب منفصل',
        ] as $locale => $expected) {
            $sections = $this->get(route('legal.account-deletion', ['locale' => $locale, 'format' => 'json']))
                ->assertOk()
                ->json('sections');
            $body = implode("\n", array_merge(...array_column($sections, 'body')));

            $this->assertStringContainsString($expected, $body);
        }
    }

    public function test_arabic_account_deletion_explains_web_access_and_identity_without_reversing_meaning(): void
    {
        $sections = $this->get(route('legal.account-deletion', ['locale' => 'ar', 'format' => 'json']))
            ->assertOk()
            ->json('sections');
        $body = implode("\n", array_merge(...array_column($sections, 'body')));

        $this->assertStringContainsString('عبر موقع Airmius أو تطبيقه', $body);
        $this->assertStringContainsString('لا يُنفَّذ الحذف إلا بعد تسجيل الدخول', $body);
        $this->assertStringContainsString('البيانات التي تُحذف أو تُزال عنها الهوية',
            implode("\n", array_column($sections, 'title')));
        $this->assertStringContainsString('عنوان البريد الإلكتروني المرتبط بالحساب إلى', $body);
        $this->assertStringContainsString('إذا لم يعد بإمكانك الوصول إلى عنوان البريد الإلكتروني هذا', $body);
        $this->assertStringContainsString('شكوك مبررة بشأن هويتك', $body);
        $this->assertStringNotContainsString('النسخة الورقية', $body);
        $this->assertStringNotContainsString('بريدك الإلكتروني المُعَطَّل', $body);
    }

    public function test_french_account_deletion_has_clear_identity_and_contact_instructions(): void
    {
        $sections = $this->get(route('legal.account-deletion', ['locale' => 'fr', 'format' => 'json']))
            ->assertOk()
            ->json('sections');
        $body = implode("\n", array_merge(...array_column($sections, 'body')));

        $this->assertStringContainsString('La simple consultation de cette page ne supprime aucune donnée.', $body);
        $this->assertStringContainsString('les liens de connexion via des services tiers', $body);
        $this->assertStringContainsString("depuis l'adresse e-mail associée au compte à", $body);
        $this->assertStringContainsString('datenschutz@airmius.com', $body);
        $this->assertStringNotContainsString("l'account affecté", $body);
    }

    public function test_manual_erasure_request_deadline_and_alternative_contact_are_explained(): void
    {
        foreach ([
            'de' => ['einer erreichbaren Adresse', 'innerhalb eines Monats', 'zwei weitere Monate'],
            'en' => ['an address where we can reach you', 'within one month', 'two further months'],
            'fr' => ['une adresse à laquelle nous pouvons vous joindre', 'dans un mois', 'deux mois supplémentaires'],
            'ar' => ['عنوان يمكننا التواصل معك', 'خلال شهر', 'شهرين إضافيين'],
        ] as $locale => $expectedPhrases) {
            $sections = $this->get(route('legal.account-deletion', ['locale' => $locale, 'format' => 'json']))
                ->assertOk()
                ->json('sections');
            $body = implode("\n", array_merge(...array_column($sections, 'body')));

            foreach ($expectedPhrases as $phrase) {
                $this->assertStringContainsString($phrase, $body);
            }
        }
    }

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

    public function test_account_deletion_form_copy_matches_club_and_retention_exceptions_in_every_locale(): void
    {
        $form = file_get_contents(resource_path('js/Pages/Profile/Partials/DeleteUserForm.vue'));
        $this->assertIsString($form);
        $this->assertStringContainsString("t('settings.delete_account.confirm_delete')", $form);

        foreach ([
            'de' => ['Verein', 'aufzubewahrende'],
            'en' => ['club', 'legal retention'],
            'fr' => ['club', 'obligation légale'],
            'ar' => ['النادي', 'القانون'],
        ] as $locale => [$clubTerm, $retentionTerm]) {
            $catalog = json_decode(file_get_contents(resource_path("js/lang/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);
            $copy = data_get($catalog, 'settings.delete_account');
            foreach (['body', 'guide', 'identity_email', 'identity_password', 'confirm_delete'] as $key) {
                $this->assertNotEmpty($copy[$key] ?? null, "Missing {$locale} account deletion {$key}.");
            }
            $this->assertStringContainsString($clubTerm, $copy['body']);
            $this->assertStringContainsString($retentionTerm, $copy['body']);
        }

        foreach (['fr', 'ar'] as $locale) {
            $catalog = json_decode(file_get_contents(resource_path("js/lang/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);
            $copy = data_get($catalog, 'settings.delete_account');
            $this->assertStringNotContainsString('toutes les ressources et données seront supprimées définitivement', $copy['body'].$copy['identity_email'].$copy['identity_password']);
            $this->assertStringNotContainsString('جميع الموارد والبيانات نهائياً', $copy['body'].$copy['identity_email'].$copy['identity_password']);
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
