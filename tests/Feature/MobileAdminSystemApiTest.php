<?php

namespace Tests\Feature;

use App\Models\ExternalProviderUsageEvent;
use App\Models\Permission;
use App\Models\Setting;
use App\Models\User;
use App\Support\EmailTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileAdminSystemApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_manager_receives_safe_settings_and_provider_cost_dashboard(): void
    {
        config([
            'airmius_ai.providers.test_provider' => [
                'label' => 'Safe AI',
                'model' => 'safe-model',
                'api_key' => 'must-not-leak',
            ],
            'airmius_ai.primary_provider' => 'test_provider',
        ]);
        $admin = $this->manager();
        Sanctum::actingAs($admin);
        ExternalProviderUsageEvent::query()->create([
            'user_id' => $admin->id,
            'area' => 'routing',
            'provider' => 'graphhopper',
            'service' => 'directions',
            'operation' => 'route',
            'billable_quantity' => 25,
            'occurred_at' => now(),
        ]);
        Setting::setValue('maintenance_mode', true);
        Setting::setValue('billing_company_name', 'Airmius Test GmbH');

        $response = $this->getJson('/api/v1/admin/system')
            ->assertOk()
            ->assertJsonPath('data.abilities.manage', true)
            ->assertJsonPath('data.settings.maintenance.enabled', true)
            ->assertJsonPath(
                'data.settings.billing.company_name',
                'Airmius Test GmbH'
            )
            ->assertJsonFragment([
                'label' => 'Safe AI',
                'has_api_key' => true,
                'model' => 'safe-model',
            ])
            ->assertJsonPath('data.provider_costs.totals.events', 1)
            ->assertJsonPath(
                'data.provider_costs.usageRows.0.provider',
                'graphhopper'
            );

        $this->assertStringNotContainsString(
            'must-not-leak',
            $response->getContent()
        );
    }

    public function test_system_manager_can_update_real_settings_through_mobile_api(): void
    {
        $admin = $this->manager();
        Sanctum::actingAs($admin);
        $templates = EmailTemplate::defaultsForValidation();
        $templates['account_welcome']['subject'] = 'Willkommen mobil';

        $this->putJson('/api/v1/admin/system/settings', [
            'maintenance_enabled' => true,
            'maintenance_title' => 'Kurze Wartung',
            'maintenance_message' => 'Wir sind gleich wieder für dich da.',
            'billing_brand_name' => 'Airmius',
            'billing_company_name' => 'Airmius Mobile GmbH',
            'billing_legal_name' => 'Airmius Mobile GmbH',
            'billing_company_street' => 'Sportweg 1',
            'billing_company_postal_code' => '10115',
            'billing_company_city' => 'Berlin',
            'billing_company_country' => 'Deutschland',
            'billing_company_email' => 'rechnung@example.test',
            'billing_company_website' => 'airmius.example.test',
            'billing_tax_number' => 'TAX-1',
            'billing_vat_id' => 'de 123 456',
            'billing_court' => 'Berlin',
            'billing_registration_number' => 'HRB 1',
            'billing_managing_director' => 'Ari Admin',
            'billing_small_business_notice' => '',
            'billing_invoice_note' => 'Danke.',
            'billing_bank_account_holder' => 'Airmius Mobile GmbH',
            'billing_bank_name' => 'Testbank',
            'billing_iban' => 'de89 3704 0044 0532 0130 00',
            'billing_bic' => 'cobadeffxxx',
            'billing_payment_terms_days' => 14,
            'email_templates' => $templates,
        ])
            ->assertOk()
            ->assertJsonPath('data.maintenance.enabled', true)
            ->assertJsonPath(
                'data.billing.company_name',
                'Airmius Mobile GmbH'
            );

        $this->assertSame(
            'DE89370400440532013000',
            Setting::valueFor('billing_iban')
        );
        $this->assertSame('COBADEFFXXX', Setting::valueFor('billing_bic'));
        $this->assertSame(
            'Willkommen mobil',
            EmailTemplate::content('account_welcome')['subject']
        );
    }

    public function test_system_api_requires_authentication_and_permission(): void
    {
        $this->getJson('/api/v1/admin/system')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/system')->assertForbidden();
        $this->putJson('/api/v1/admin/system/settings', [])
            ->assertForbidden();
    }

    private function manager(): User
    {
        Permission::findOrCreate('system.manage', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('system.manage');

        return $user;
    }
}
