<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use App\Support\CountryCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CountryCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_contains_all_iso_entries_and_matches_mobile_asset(): void
    {
        $rows = CountryCatalog::defaults();
        $this->assertCount(250, $rows);
        $this->assertCount(250, array_unique(array_column($rows, 'code')));
        $this->assertSame($rows, json_decode(file_get_contents(base_path('mobile/airmius_mobile/assets/data/countries.json')), true));
        foreach ($rows as $row) {
            $this->assertMatchesRegularExpression('/^[A-Z]{2}$/', $row['code']);
            foreach (['de', 'en', 'fr', 'ar'] as $locale) {
                $this->assertNotEmpty($row['names'][$locale]);
            }
        }
        $this->getJson('/country-catalog')->assertOk()->assertJsonCount(250, 'data')->assertJsonPath('can_manage', false);
        $this->getJson('/api/v1/country-catalog')->assertOk()->assertJsonCount(250, 'data');
        $this->assertSame('Deutschland', collect($rows)->firstWhere('code', 'DE')['names']['de']);
        foreach (['AX', 'AQ', 'BQ', 'PS', 'TW', 'VA', 'XK', 'ZW'] as $code) {
            $this->assertContains($code, array_column($rows, 'code'));
        }
    }

    public function test_only_system_managers_can_extend_the_shared_catalog(): void
    {
        $payload = ['code' => 'xx', 'names' => ['de' => 'Testgebiet', 'en' => 'Test territory']];
        $this->postJson('/api/v1/country-catalog', $payload)->assertUnauthorized();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/country-catalog', $payload)->assertForbidden();
        Permission::findOrCreate('system.manage', 'web');
        $user->givePermissionTo('system.manage');
        $this->postJson('/api/v1/country-catalog', $payload)->assertOk()->assertJsonCount(251, 'data')->assertJsonPath('can_manage', true);
        $this->getJson('/country-catalog')->assertOk()->assertJsonFragment(['code' => 'XX', 'names' => $payload['names']]);
        $this->postJson('/api/v1/country-catalog', ['code' => 'DE', 'names' => $payload['names']])->assertUnprocessable();
        $this->postJson('/api/v1/country-catalog', ['code' => 'ABC', 'names' => $payload['names']])->assertUnprocessable();
        $this->postJson('/api/v1/country-catalog', ['code' => 'XY', 'names' => ['de' => '']])->assertUnprocessable();
    }

    public function test_web_admin_can_add_an_entry_that_mobile_reads(): void
    {
        Permission::findOrCreate('system.manage', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('system.manage');
        $this->actingAs($user)->postJson('/country-catalog', [
            'code' => 'XY', 'names' => ['de' => 'Weiteres Gebiet', 'en' => 'Additional territory'],
        ])->assertOk();
        $this->getJson('/api/v1/country-catalog')->assertOk()->assertJsonCount(251, 'data')->assertJsonFragment(['code' => 'XY']);
    }
}
