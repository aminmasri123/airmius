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
        $arabic = file_get_contents(resource_path('js/lang/ar.json'));
        $sports = file_get_contents(resource_path('js/lang/sports.js'));

        $this->assertIsString($arabic);
        $this->assertIsString($sports);
        $this->assertDoesNotMatchRegularExpression('/�|\?{2,}/u', $arabic);
        $this->assertStringNotContainsString('�', $sports);
        $this->assertMatchesRegularExpression('/\p{Arabic}/u', $arabic);
        $this->assertMatchesRegularExpression('/\p{Arabic}/u', $sports);
    }
}
