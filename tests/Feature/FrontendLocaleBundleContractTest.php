<?php

namespace Tests\Feature;

use Illuminate\Support\Arr;
use Tests\TestCase;

class FrontendLocaleBundleContractTest extends TestCase
{
    private const LOCALES = ['de', 'en', 'fr', 'ar'];

    public function test_semantic_and_automatic_locale_catalogs_are_split_with_key_parity(): void
    {
        $coreCatalogs = [];
        $automaticCatalogs = [];

        foreach (self::LOCALES as $locale) {
            $coreCatalogs[$locale] = json_decode(
                (string) file_get_contents(resource_path("js/lang/{$locale}.json")),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
            $automaticCatalogs[$locale] = json_decode(
                (string) file_get_contents(resource_path("js/lang/auto/{$locale}.json")),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            $this->assertArrayNotHasKey('auto', $coreCatalogs[$locale]);
            $this->assertArrayNotHasKey('auto_patterns', $coreCatalogs[$locale]);
            $this->assertIsArray($automaticCatalogs[$locale]['auto'] ?? null);
            $this->assertIsArray($automaticCatalogs[$locale]['auto_patterns'] ?? null);
        }

        $coreKeys = collect(array_keys($coreCatalogs['de']))->sort()->values()->all();
        $automaticKeys = collect(array_keys($automaticCatalogs['de']['auto']))->sort()->values()->all();

        foreach (self::LOCALES as $locale) {
            $this->assertSame(
                $coreKeys,
                collect(array_keys($coreCatalogs[$locale]))->sort()->values()->all(),
                "{$locale} semantic key parity",
            );
            $this->assertSame(
                $automaticKeys,
                collect(array_keys($automaticCatalogs[$locale]['auto']))->sort()->values()->all(),
                "{$locale} automatic key parity",
            );
        }

        $referencePatternSignatures = collect($automaticCatalogs['en']['auto_patterns'])
            ->map(fn (array $pattern) => [$pattern['source'] ?? null, $pattern['flags'] ?? null])
            ->all();
        $this->assertSame([], $automaticCatalogs['de']['auto_patterns']);
        $this->assertNotEmpty($referencePatternSignatures);

        foreach (['fr', 'ar'] as $locale) {
            $this->assertSame(
                $referencePatternSignatures,
                collect($automaticCatalogs[$locale]['auto_patterns'])
                    ->map(fn (array $pattern) => [$pattern['source'] ?? null, $pattern['flags'] ?? null])
                    ->all(),
                "{$locale} automatic pattern parity",
            );
        }
    }

    public function test_client_loads_semantic_copy_first_and_automatic_copy_as_progressive_enhancement(): void
    {
        $bootstrap = (string) file_get_contents(resource_path('js/app.js'));
        $languageService = (string) file_get_contents(resource_path('js/services/i18nService.js'));
        $languageDropdown = (string) file_get_contents(resource_path('js/Components/LanguageDropdown.vue'));
        $responseMiddleware = (string) file_get_contents(app_path('Http/Middleware/TranslateUserFacingResponseText.php'));

        $this->assertStringContainsString("import.meta.glob('./lang/*.json')", $bootstrap);
        $this->assertStringContainsString("import.meta.glob('./lang/auto/*.json')", $bootstrap);
        $this->assertStringContainsString('await loadLocaleMessages(i18n, initialLocale)', $bootstrap);
        $this->assertStringContainsString('requestIdleCallback', $bootstrap);
        $this->assertStringContainsString('scheduleAutoLocale(activeInitialLocale', $bootstrap);
        $this->assertStringContainsString('registerLocaleActivator(activateLocale)', $bootstrap);
        $this->assertStringContainsString("normalizedLocale === 'de'", $bootstrap);
        $this->assertStringNotContainsString('locale.value = newLocale', $languageService);
        $this->assertStringContainsString('onError: reject', $languageService);
        $this->assertStringContainsString(':aria-busy="changing"', $languageDropdown);
        $this->assertStringContainsString('await changeLang(code)', $languageDropdown);
        $this->assertStringContainsString('js/lang/auto/{$locale}.json', $responseMiddleware);
    }

    public function test_admin_commerce_copy_is_lazy_complete_and_registered_for_every_locale(): void
    {
        $copy = json_decode(
            (string) file_get_contents(resource_path('js/i18n/adminCommerceLocalization.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $referenceKeys = collect(array_keys(Arr::dot($copy['de'])))->sort()->values()->all();

        foreach (self::LOCALES as $locale) {
            $this->assertSame(
                $referenceKeys,
                collect(array_keys(Arr::dot($copy[$locale])))->sort()->values()->all(),
                "{$locale} lazy admin commerce key parity",
            );
        }

        $page = (string) file_get_contents(resource_path('js/Pages/Auth/Dashboard/Admin/Commerce/Index.vue'));
        $this->assertStringContainsString('@/i18n/adminCommerceLocalization.json', $page);
        $this->assertStringContainsString('mergeLocaleMessage(locale, { adminCommerce: messages })', $page);
    }

    public function test_sponsor_admin_copy_has_complete_locale_parity_and_covers_page_keys(): void
    {
        $copy = json_decode((string) file_get_contents(resource_path('js/i18n/sponsorsAdminLocalization.json')), true, flags: JSON_THROW_ON_ERROR);
        $page = (string) file_get_contents(resource_path('js/Pages/Auth/Dashboard/Sponsors/Index.vue'));
        preg_match_all('/sponsors_admin\.([a-zA-Z0-9_]+)[\'"]/', $page, $matches);
        $reference = array_keys(Arr::dot($copy['de']));
        sort($reference);
        foreach (self::LOCALES as $locale) {
            $messages = Arr::dot($copy[$locale]);
            $keys = array_keys($messages);
            sort($keys);
            $this->assertSame($reference, $keys, "{$locale} sponsor key parity");
            foreach (array_unique($matches[1]) as $key) {
                $this->assertArrayHasKey($key, $messages);
                $this->assertNotSame('', trim($messages[$key]));
            }
            $core = json_decode((string) file_get_contents(resource_path("js/lang/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);
            $this->assertArrayNotHasKey('sponsors_admin', $core);
        }
        $this->assertStringContainsString('@/i18n/sponsorsAdminLocalization.json', $page);
        $this->assertStringContainsString('mergeLocaleMessage(language, { sponsors_admin: messages })', $page);
    }

    public function test_production_manifest_keeps_core_and_automatic_catalogs_as_separate_dynamic_chunks(): void
    {
        // CI can verify its isolated build; the default still checks deployed assets.
        $buildPath = getenv('AIRMIUS_TEST_FRONTEND_BUILD_DIR') ?: public_path('build');
        $manifest = json_decode(
            (string) file_get_contents($buildPath.'/manifest.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $entry = $manifest['resources/js/app.js'];
        $sponsorPage = 'resources/js/Pages/Auth/Dashboard/Sponsors/Index.vue';
        $this->assertContains($sponsorPage, $entry['dynamicImports']);
        $this->assertTrue($manifest[$sponsorPage]['isDynamicEntry'] ?? false);
        $this->assertStringContainsString('sponsors_admin', (string) file_get_contents($buildPath.'/'.$manifest[$sponsorPage]['file']));
        $pending = $entry['imports'] ?? [];
        $visited = [];
        while ($pending !== []) {
            $dependency = array_pop($pending);
            if (isset($visited[$dependency])) {
                continue;
            }
            $visited[$dependency] = true;
            $this->assertNotSame($sponsorPage, $dependency, 'Sponsor copy must not become an eager application dependency.');
            $pending = array_merge($pending, $manifest[$dependency]['imports'] ?? []);
        }

        foreach (self::LOCALES as $locale) {
            foreach (["resources/js/lang/{$locale}.json", "resources/js/lang/auto/{$locale}.json"] as $source) {
                $this->assertContains($source, $entry['dynamicImports']);
                $this->assertTrue($manifest[$source]['isDynamicEntry'] ?? false);
                $this->assertFileExists($buildPath.'/'.$manifest[$source]['file']);
            }

            $this->assertLessThan(
                312_000,
                filesize($buildPath.'/'.$manifest["resources/js/lang/{$locale}.json"]['file']),
                "{$locale} render-blocking locale chunk is unexpectedly large",
            );
        }

        $this->assertSame([], array_values(array_filter(
            $entry['imports'] ?? [],
            fn (string $import) => str_contains($import, '/lang/'),
        )));
    }
}
