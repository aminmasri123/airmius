<?php

namespace Tests\Feature;

use App\Support\FinanceImplementationInventory;
use Tests\TestCase;

class FinanceImplementationInventoryTest extends TestCase
{
    public function test_meta_exposes_complete_t003_to_t008_finance_inventory_without_closing_followups(): void
    {
        $catalog = $this->getJson('/api/v1/meta')
            ->assertOk()
            ->json('data.catalogs.finance_inventory');

        $this->assertSame(FinanceImplementationInventory::VERSION, $catalog['version']);

        $coverage = collect($catalog['coverage'])->keyBy('key');

        foreach (['receivables', 'payment_methods', 'allocation', 'sepa', 'returns', 'dunning', 'refunds', 'deduplication'] as $key) {
            $this->assertSame('inventoried', $coverage[$key]['status'], "{$key} must be inventoried.");
            $this->assertNotEmpty($coverage[$key]['artifacts'], "{$key} needs concrete artifacts.");
            $this->assertNotEmpty($coverage[$key]['contract_fields'], "{$key} needs contract fields.");
        }

        $this->assertContains('ClubInvoicePaymentService', $coverage['allocation']['artifacts']);
        $this->assertContains('ClubSepaSettlement', $coverage['returns']['artifacts']);
        $this->assertContains('ClubSepaFeeRechargeCredit', $coverage['refunds']['artifacts']);
        $this->assertContains('unified_receivable_payment_status_machine', $catalog['open_followups']);
    }
}
