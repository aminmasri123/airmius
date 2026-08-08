<?php

namespace Tests\Feature;

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
        $this->assertStringContainsString("await changeLang(code)", $languageDropdown);
        $this->assertStringContainsString('js/lang/auto/{$locale}.json', $responseMiddleware);
    }

    public function test_production_manifest_keeps_core_and_automatic_catalogs_as_separate_dynamic_chunks(): void
    {
        $manifest = json_decode(
            (string) file_get_contents(public_path('build/manifest.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $entry = $manifest['resources/js/app.js'];

        foreach (self::LOCALES as $locale) {
            foreach (["resources/js/lang/{$locale}.json", "resources/js/lang/auto/{$locale}.json"] as $source) {
                $this->assertContains($source, $entry['dynamicImports']);
                $this->assertTrue($manifest[$source]['isDynamicEntry'] ?? false);
                $this->assertFileExists(public_path('build/'.$manifest[$source]['file']));
            }

            $this->assertLessThan(
                310_000,
                filesize(public_path('build/'.$manifest["resources/js/lang/{$locale}.json"]['file'])),
                "{$locale} render-blocking locale chunk is unexpectedly large",
            );
        }

        $this->assertSame([], array_values(array_filter(
            $entry['imports'] ?? [],
            fn (string $import) => str_contains($import, '/lang/'),
        )));
    }
}
