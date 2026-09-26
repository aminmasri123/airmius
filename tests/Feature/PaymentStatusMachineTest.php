<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Invoice;
use App\Models\PaymentBookingReceipt;
use App\Models\User;
use App\Services\ClubInvoicePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class PaymentStatusMachineTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_transfer_direct_debit_and_online_payments_use_unified_claim_statuses_without_breaking_legacy_status(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);

        $bank = $this->invoice($club, 'BANK-001');
        $sepa = $this->invoice($club, 'SEPA-001');
        $online = $this->invoice($club, 'ONLINE-001');

        $service = app(ClubInvoicePaymentService::class);
        $service->record($bank, ['amount' => 10, 'method' => 'bank_transfer', 'status' => 'pending'], $owner);
        $service->record($sepa, ['amount' => 10, 'method' => 'sepa_debit', 'status' => 'pending'], $owner);
        $service->record($online, ['amount' => 10, 'method' => 'online', 'status' => 'pending'], $owner);

        $this->assertSame('open', $bank->fresh()->status);
        $this->assertSame('awaiting_transfer', $bank->fresh()->claim_status);
        $this->assertSame('awaiting_direct_debit', $sepa->fresh()->claim_status);
        $this->assertSame('processing_online', $online->fresh()->claim_status);
        $this->assertSame([
            'awaiting_transfer',
            'awaiting_direct_debit',
            'processing_online',
        ], PaymentBookingReceipt::query()->orderBy('id')->pluck('claim_status_after')->all());
    }

    public function test_cash_settlement_and_correction_create_chained_immutable_booking_receipts(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $invoice = $this->invoice($club, 'CASH-001', '100.00');

        $service = app(ClubInvoicePaymentService::class);
        $payment = $service->record($invoice, ['amount' => 40, 'method' => 'cash'], $owner);
        $this->assertSame('open', $invoice->fresh()->status);
        $this->assertSame('partially_paid', $invoice->fresh()->claim_status);

        $service->correct($payment, ['amount' => 100, 'method' => 'cash'], $owner);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('paid', $invoice->fresh()->claim_status);

        $receipts = PaymentBookingReceipt::query()->orderBy('id')->get();
        $this->assertCount(2, $receipts);
        $this->assertNull($receipts[0]->previous_hash);
        $this->assertSame($receipts[0]->hash, $receipts[1]->previous_hash);
        $this->assertSame('recorded', $receipts[0]->action);
        $this->assertSame('corrected', $receipts[1]->action);

        $this->expectException(LogicException::class);
        $receipts[0]->update(['reference' => 'tampered']);
    }

    private function invoice(Club $club, string $number, string $amount = '10.00'): Invoice
    {
        return Invoice::query()->create([
            'club_id' => $club->id,
            'user_id' => $club->owner_id,
            'number' => $number,
            'title' => 'Mitgliedsbeitrag',
            'amount' => $amount,
            'status' => 'open',
            'source' => 'manual',
            'due_date' => now()->addDays(7),
            'issued_at' => now(),
        ]);
    }
}
