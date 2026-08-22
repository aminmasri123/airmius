<?php

namespace Tests\Unit;

use Illuminate\Support\Arr;
use Tests\TestCase;

class ClubInventoryWebContractTest extends TestCase
{
    public function test_inventory_workspace_covers_ajax_items_loans_returns_qr_and_maintenance(): void
    {
        $source = file_get_contents(resource_path('js/Pages/Auth/Dashboard/ClubInventory/Index.vue'));

        foreach (['inventory.index', 'inventory.store', 'inventory.update', 'inventory.checkout', 'inventory.loans.${action}', 'inventory.maintenance.store', 'inventory.maintenance.update'] as $route) {
            $this->assertStringContainsString($route, $source);
        }
        foreach (['items', 'loans', 'maintenance'] as $tab) {
            $this->assertStringContainsString("key: '{$tab}'", $source);
        }
        $this->assertStringContainsString('qr_svg_data_uri', $source);
        $this->assertStringContainsString('role="tablist"', $source);
        $this->assertStringContainsString('role="dialog"', $source);
        $this->assertStringContainsString('@/i18n/clubInventoryLocalization.json', $source);
        $this->assertStringContainsString('mergeLocaleMessage(language, { club_inventory: messages })', $source);
        $this->assertStringNotContainsString('window.location.reload', $source);
    }

    public function test_inventory_page_catalog_has_locale_key_and_placeholder_parity(): void
    {
        $catalog = json_decode(
            (string) file_get_contents(resource_path('js/i18n/clubInventoryLocalization.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $referenceMessages = Arr::dot($catalog['de']);
        $reference = collect(array_keys($referenceMessages))->sort()->values()->all();

        foreach (['de', 'en', 'fr', 'ar'] as $locale) {
            $messages = Arr::dot($catalog[$locale]);
            $this->assertSame($reference, collect(array_keys($messages))->sort()->values()->all(), "{$locale} inventory keys");

            foreach ($messages as $key => $value) {
                preg_match_all('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', (string) $referenceMessages[$key], $sourcePlaceholders);
                preg_match_all('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', (string) $value, $targetPlaceholders);
                sort($sourcePlaceholders[0]);
                sort($targetPlaceholders[0]);
                $this->assertSame($sourcePlaceholders[0], $targetPlaceholders[0], "{$locale}.{$key} placeholders");
            }
        }
    }
}
