<?php

namespace Tests\Feature;

use App\Models\BankTransaction;
use App\Models\Club;
use App\Models\ClubFinanceEntry;
use App\Models\Invoice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\ClubInvoicePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinanceAccountingContractRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_payload_keeps_accounting_contract_for_cash_bank_invoices_and_open_items(): void
    {
        Notification::fake();
        [$club, $owner, $member] = $this->clubWithFinanceAccess();
        Sanctum::actingAs($owner);

        $outgoingInvoice = Invoice::query()->create([
            'club_id' => $club->id,
            'user_id' => $member->id,
            'number' => 'AR-2026-0001',
            'title' => 'Mitgliedsbeitrag 2026',
            'amount' => '120.00',
            'status' => 'open',
            'source' => 'membership_contribution',
            'issued_at' => now()->subDays(3),
            'due_date' => now()->addDays(14),
        ]);

        $payment = app(ClubInvoicePaymentService::class)->record($outgoingInvoice, [
            'amount' => '70.00',
            'method' => 'bank_transfer',
            'reference' => 'BANK-AR-2026-0001',
        ], $owner);

        BankTransaction::query()->create([
            'club_id' => $club->id,
            'invoice_id' => $outgoingInvoice->id,
            'payment_id' => $payment->id,
            'imported_by' => $owner->id,
            'transaction_hash' => hash('sha256', 'AR-2026-0001-70'),
            'booking_date' => now()->toDateString(),
            'amount' => '70.00',
            'currency' => 'EUR',
            'debtor_name' => $member->name,
            'purpose' => 'AR-2026-0001 Teilzahlung',
            'status' => 'matched',
            'match_confidence' => 100,
            'match_reason' => 'invoice-number-and-amount',
        ]);

        ClubFinanceEntry::query()->create([
            'club_id' => $club->id,
            'user_id' => $owner->id,
            'type' => 'income',
            'account' => 'cash',
            'category' => 'event_cashbox',
            'title' => 'Sommerfest Barkasse',
            'amount' => '30.00',
            'booked_on' => now()->toDateString(),
            'reference' => 'CASH-2026-09',
        ]);

        ClubFinanceEntry::query()->create([
            'club_id' => $club->id,
            'user_id' => $owner->id,
            'type' => 'expense',
            'account' => 'bank',
            'category' => 'supplier_invoice',
            'title' => 'Eingangsrechnung Hallenmiete',
            'amount' => '45.00',
            'booked_on' => now()->toDateString(),
            'reference' => 'AP-2026-0007',
            'description' => 'Lieferantenbeleg ohne Mitgliederforderung.',
        ]);

        $payload = $this->postJson("/api/v1/clubs/{$club->id}/finance-entries", [
            'type' => 'expense',
            'account' => 'cash',
            'category' => 'receipt',
            'title' => 'Kassenbeleg Porto',
            'amount' => '5.00',
            'booked_on' => now()->toDateString(),
            'reference' => 'CASH-OUT-2026-09',
        ])->assertOk()->json('data');

        $invoice = collect($payload['invoices'])->firstWhere('number', 'AR-2026-0001');
        $this->assertSame('open', $invoice['status']);
        $this->assertSame('70.00', $invoice['received_amount']);
        $this->assertSame('50.00', $invoice['outstanding_amount']);
        $this->assertTrue($invoice['is_partially_paid']);

        $this->assertEquals(50.0, $payload['invoice_summary']['open_amount']);
        $this->assertEquals(70.0, $payload['invoice_summary']['paid_amount']);
        $this->assertSame(1, $payload['invoice_summary']['open_count']);
        $this->assertSame(0, $payload['invoice_summary']['paid_count']);

        $this->assertEquals(25.0, $payload['summary']['cash_balance']);
        $this->assertEquals(25.0, $payload['summary']['bank_balance']);
        $this->assertEquals(50.0, $payload['summary']['total_balance']);
        $this->assertEquals(100.0, $payload['summary']['income_total']);
        $this->assertEquals(50.0, $payload['summary']['expense_total']);
        $this->assertEquals(50.0, $payload['summary']['open_invoice_amount']);
        $this->assertSame(1, $payload['summary']['open_invoices_count']);

        $this->assertNotNull(collect($payload['payments'])->first(fn (array $row) => $row['invoice_id'] === $outgoingInvoice->id
            && $row['method'] === 'bank_transfer'
            && $row['amount'] === '70.00'
            && $row['purpose'] === 'membership_invoice'));

        $this->assertNotNull(collect($payload['bank_transactions'])->first(fn (array $row) => $row['invoice_id'] === $outgoingInvoice->id
            && $row['payment_id'] === $payment->id
            && $row['status'] === 'matched'
            && $row['match_confidence'] === 100));

        $entries = collect($payload['finance_entries']);
        $this->assertNotNull($entries->first(fn (array $row) => $row['type'] === 'income'
            && $row['account'] === 'cash'
            && $row['category'] === 'event_cashbox'
            && $row['amount'] === '30.00'));
        $this->assertNotNull($entries->first(fn (array $row) => $row['type'] === 'expense'
            && $row['account'] === 'bank'
            && $row['category'] === 'supplier_invoice'
            && $row['reference'] === 'AP-2026-0007'));
        $this->assertNotNull($entries->first(fn (array $row) => $row['type'] === 'expense'
            && $row['account'] === 'cash'
            && $row['category'] === 'receipt'
            && $row['reference'] === 'CASH-OUT-2026-09'));
    }

    private function clubWithFinanceAccess(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);

        $club->users()->attach($member->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]);

        $plan = SubscriptionPlan::query()->firstOrCreate(['slug' => 'pro'], [
            'target_actor' => 'verein',
            'name' => 'Pro',
            'monthly_price_cents' => 2990,
            'yearly_price_cents' => 29900,
            'currency' => 'EUR',
            'features' => [],
            'sort_order' => 1,
            'is_public' => true,
            'is_active' => true,
        ]);

        $club->currentSubscription()->updateOrCreate([], [
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'billing_interval' => 'monthly',
        ]);
        $club->unsetRelation('currentSubscription');

        return [$club, $owner, $member];
    }
}
