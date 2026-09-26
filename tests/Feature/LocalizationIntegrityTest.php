<?php

namespace Tests\Feature;

use App\Support\LocalizationReadinessReport;
use Tests\TestCase;

class LocalizationIntegrityTest extends TestCase
{
    public function test_supported_frontend_locales_match_the_german_reference(): void
    {
        $integrity = LocalizationReadinessReport::make()['translation_integrity'];

        $this->assertSame('de', $integrity['source_locale']);
        $this->assertTrue($integrity['source_key_parity']);
        $this->assertTrue($integrity['automatic_ui_key_parity']);
        $this->assertTrue($integrity['placeholder_parity']);
        $this->assertSame(0, $integrity['corrupt_target_values']);

        foreach (['en', 'fr', 'ar'] as $locale) {
            $this->assertSame([], $integrity['locales'][$locale]['missing_source_keys'], "{$locale} missing frontend keys");
            $this->assertSame([], $integrity['locales'][$locale]['missing_automatic_ui_keys'], "{$locale} missing auto UI keys");
            $this->assertSame(0, $integrity['locales'][$locale]['placeholder_mismatches'], "{$locale} placeholder drift");
        }
    }

    public function test_locale_fallback_rtl_and_club_specific_terms_are_explicitly_guarded(): void
    {
        $report = LocalizationReadinessReport::make();

        $this->assertSame(['de', 'en', 'fr', 'ar'], $report['supported_locales']);
        $this->assertSame(['ar'], $report['rtl_locales']);
        $this->assertSame(config('app.fallback_locale'), $report['fallback_locale']);
        $this->assertTrue($report['quality_gates']['mobile_contract_exports_rtl']);
        $this->assertTrue($report['quality_gates']['rtl_manual_qa_required']);

        $this->assertFileExists(base_path('docs/CLUB_METADATA_CONFIGURATION.md'));
        $this->assertFileExists(base_path('resources/js/i18n/clubMetadataLocalization.json'));

        $metadataCatalog = json_decode(
            (string) file_get_contents(base_path('resources/js/i18n/clubMetadataLocalization.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $referenceKeys = array_keys($metadataCatalog['de']);

        foreach (['en', 'fr', 'ar'] as $locale) {
            $this->assertSame($referenceKeys, array_keys($metadataCatalog[$locale]), "club metadata terms:{$locale}");
            $this->assertNotContains('', array_map('trim', $metadataCatalog[$locale]));
        }

        $this->assertMatchesRegularExpression('/\p{Arabic}/u', implode(' ', $metadataCatalog['ar']));
    }

    public function test_arabic_catalog_and_sport_names_have_no_corruption_markers(): void
    {
        $arabic = file_get_contents(resource_path('js/lang/ar.json'))
            .file_get_contents(resource_path('js/lang/auto/ar.json'));
        $sports = file_get_contents(resource_path('js/lang/sports.js'));

        $this->assertIsString($arabic);
        $this->assertIsString($sports);
        $this->assertDoesNotMatchRegularExpression('/�|\?{2,}/u', $arabic);
        $this->assertStringNotContainsString('�', $sports);
        $this->assertMatchesRegularExpression('/\p{Arabic}/u', $arabic);
        $this->assertMatchesRegularExpression('/\p{Arabic}/u', $sports);
    }

    public function test_french_guest_and_guardian_copy_has_no_known_accent_or_separator_corruption(): void
    {
        $french = file_get_contents(resource_path('js/lang/fr.json'))
            .file_get_contents(resource_path('js/lang/auto/fr.json'));

        $this->assertIsString($french);
        $this->assertStringNotContainsString('làgal', $french);
        $this->assertStringNotContainsString('dés le départ', $french);
        $this->assertStringNotContainsString("droits · l'image", $french);
        $this->assertStringNotContainsString('clubs · gagner', $french);
    }

    public function test_localization_debt_excludes_verified_runtime_catalog_coverage(): void
    {
        $report = LocalizationReadinessReport::make();

        $this->assertSame(
            $report['vue']['visible_text_source_candidates'],
            $report['vue']['visible_text_candidates'] + $report['vue']['runtime_auto_covered_visible_text_candidates'],
        );
        $this->assertSame(
            $report['vue']['attribute_text_source_candidates'],
            $report['vue']['attribute_text_candidates'] + $report['vue']['runtime_auto_covered_attribute_text_candidates'],
        );
        $this->assertSame(
            $report['php']['response_string_source_candidates'],
            $report['php']['response_string_candidates']
                + $report['php']['runtime_auto_covered_response_string_candidates']
                + $report['php']['runtime_server_legal_covered_response_string_candidates'],
        );
        $this->assertSame(
            $report['quality_gates']['hardcoded_text_total_candidates'],
            $report['vue']['total_candidates'] + $report['php']['total_candidates'],
        );
    }

    public function test_server_localized_legal_copy_closes_the_hardcoded_text_debt(): void
    {
        $report = LocalizationReadinessReport::make();

        $this->assertSame(0, $report['vue']['total_candidates']);
        $this->assertSame(0, $report['php']['total_candidates']);
        $this->assertSame(58, $report['php']['runtime_server_legal_covered_response_string_candidates']);
        $this->assertSame([], $report['php']['top_files']);
        $this->assertSame('clean', $report['quality_gates']['hardcoded_text_budget_status']);
    }

    public function test_server_only_legal_catalogs_have_locale_reference_and_encoding_integrity(): void
    {
        $integrity = LocalizationReadinessReport::make()['legal_content_integrity'];

        $this->assertSame('localized-legal-content.v1', $integrity['contract']);
        $this->assertSame('de', $integrity['source_locale']);
        $this->assertSame(354, $integrity['source_key_count']);
        $this->assertTrue($integrity['metadata_valid']);
        $this->assertTrue($integrity['source_values_match_keys']);
        $this->assertTrue($integrity['key_parity']);
        $this->assertTrue($integrity['placeholder_parity']);
        $this->assertTrue($integrity['reference_integrity']);
        $this->assertSame(0, $integrity['untranslated_long_values']);
        $this->assertSame(0, $integrity['source_language_marker_values']);
        $this->assertSame(0, $integrity['corrupt_target_values']);
        $this->assertTrue($integrity['arabic_has_arabic_glyphs']);
        $this->assertTrue($integrity['external_legal_and_native_review_required']);
    }
}
