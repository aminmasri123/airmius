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
            $report['php']['response_string_candidates'] + $report['php']['runtime_auto_covered_response_string_candidates'],
        );
        $this->assertSame(
            $report['quality_gates']['hardcoded_text_total_candidates'],
            $report['vue']['total_candidates'] + $report['php']['total_candidates'],
        );
    }

    public function test_only_the_separately_reviewed_legal_copy_remains_in_the_localization_debt(): void
    {
        $report = LocalizationReadinessReport::make();

        $this->assertSame(0, $report['vue']['total_candidates']);
        $this->assertSame(58, $report['php']['total_candidates']);
        $this->assertSame(
            ['app/Http/Controllers/LegalPageController.php'],
            array_column($report['php']['top_files'], 'path'),
        );
    }
}
