<?php

namespace Tests\Feature;

use App\Support\ClubFinanceFacilitiesReadinessCatalog;
use Tests\TestCase;

class ClubFinanceFacilitiesReadinessCatalogTest extends TestCase
{
    public function test_meta_exposes_facilities_inventory_tariff_payment_and_accounting_contracts(): void
    {
        $catalog = $this->getJson('/api/v1/meta')
            ->assertOk()
            ->json('data.catalogs.club_finance_facilities_readiness');

        $this->assertSame(ClubFinanceFacilitiesReadinessCatalog::VERSION, $catalog['version']);
        $this->assertSame('local_contract_ready_external_gates_open', $catalog['decision']);

        $this->assertContains('loss_block', $catalog['facilities']['access_keys']);
        $this->assertContains('tenant_boundaries', $catalog['facilities']['regression_matrix']);

        $this->assertContains('affected_item_block', $catalog['inventory_fleet']['maintenance_replacement']);
        $this->assertContains('driver_permission', $catalog['inventory_fleet']['vehicle_resource']);
        $this->assertContains('inventory_migration', $catalog['inventory_fleet']['regression_matrix']);

        $this->assertContains('collective_invoice', $catalog['tariffs_billing']['alternate_payer_household']);
        $this->assertContains('sepa_mandate_reference', $catalog['tariffs_billing']['alternate_payer_household']);
        $this->assertContains('four_eyes_approval', $catalog['tariffs_billing']['hardship_installments']);
        $this->assertContains('rounding', $catalog['tariffs_billing']['regression_matrix']);

        $this->assertContains('staging_bank', $catalog['payments_acceptance']['go_no_go_sheets']);
        $this->assertContains('finance_domain_acceptance', $catalog['payments_acceptance']['go_no_go_sheets']);
        $this->assertSame('pending', $catalog['payments_acceptance']['external_completion']);

        $this->assertContains('versioned_vat_rules', $catalog['accounting_close']['tax_accounting_rules']);
        $this->assertContains('domain_approval_required_before_automation', $catalog['accounting_close']['tax_accounting_rules']);
        $this->assertContains('role_separation', $catalog['accounting_close']['invoice_review_payment_release']);
        $this->assertContains('inbound_validation', $catalog['accounting_close']['e_invoice']);
        $this->assertContains('period_lock', $catalog['accounting_close']['year_end']);
        $this->assertContains('staging_acceptance_required', $catalog['accounting_close']['year_end']);
    }
}
