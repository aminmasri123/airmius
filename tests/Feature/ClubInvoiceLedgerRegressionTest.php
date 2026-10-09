<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Models\ClubPaymentAllocation;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentBookingReceipt;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\ClubContributionInvoiceRunService;
use App\Services\ClubInvoiceCancellationService;
use App\Services\ClubInvoiceCreditService;
use App\Services\ClubInvoicePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubInvoiceLedgerRegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    private Club $club;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();
        $this->club = Club::factory()->create(['owner_id' => $this->owner->id]);
        $this->club->users()->attach($this->member, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        $plan = SubscriptionPlan::query()->firstOrCreate(['slug' => 'pro'], [
            'target_actor' => 'verein', 'name' => 'Club', 'monthly_price_cents' => 2990,
            'yearly_price_cents' => 29900, 'currency' => 'EUR', 'features' => [], 'is_active' => true,
        ]);
        $this->club->currentSubscription()->updateOrCreate([], [
            'subscription_plan_id' => $plan->id, 'status' => 'active', 'billing_interval' => 'monthly',
        ]);
        $this->invoice = $this->newInvoice();
        Sanctum::actingAs($this->owner);
    }

    private function newInvoice(array $overrides = []): Invoice
    {
        return Invoice::create([
            'club_id' => $this->club->id, 'user_id' => $this->member->id, 'membership_user_id' => $this->member->id,
            'number' => 'LEDGER-'.(Invoice::count() + 1), 'title' => 'Beitrag', 'amount' => 100,
            'status' => 'open', 'claim_status' => 'open', 'source' => 'manual',
            'due_date' => now()->addDays(7), 'issued_at' => now(), ...$overrides,
        ]);
    }

    private function statusUrl(?Invoice $invoice = null): string
    {
        return "/api/v1/clubs/{$this->club->id}/membership-invoices/".($invoice ?? $this->invoice)->id.'/status';
    }

    private function record(int $amount = 20, ?Invoice $invoice = null): Payment
    {
        return app(ClubInvoicePaymentService::class)->record($invoice ?? $this->invoice, ['amount' => $amount, 'method' => 'cash'], $this->owner);
    }

    private function cancel(string $action = 'credit', ?Invoice $invoice = null): Invoice
    {
        return app(ClubInvoiceCancellationService::class)->cancel($invoice ?? $this->invoice, $action, $this->owner);
    }

    private function applyCredit(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            ClubInvoiceCreditService::lockClub($this->club->id);
            app(ClubInvoiceCreditService::class)->apply($invoice, $this->owner);
        });
    }

    public function test_paid_status_requires_a_real_payment_in_api_and_web(): void
    {
        $this->putJson($this->statusUrl(), ['status' => 'paid'])->assertUnprocessable();
        $this->actingAs($this->owner)->putJson(route('auth.club-memberships.invoices.update', $this->invoice), ['status' => 'paid'])->assertUnprocessable();
        $this->assertSame('open', $this->invoice->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_cancelled_invoice_cannot_be_reopened_and_replacement_is_idempotent(): void
    {
        $this->record(100);
        $this->cancel();
        $this->putJson($this->statusUrl(), ['status' => 'open'])->assertUnprocessable();
        $service = app(ClubInvoiceCancellationService::class);
        $replacement = $service->replaceAccidentalCancellation($this->invoice, $this->owner);
        $this->assertSame('paid', $replacement->status);
        $this->assertSame($replacement->id, $service->replaceAccidentalCancellation($this->invoice, $this->owner)->id);
        $this->assertSame('cancelled', $this->invoice->fresh()->claim_status);
        $this->assertSame(100.0, (float) Payment::where('status', 'paid')->sum('amount'));
        $this->assertDatabaseHas('payment_booking_receipts', ['invoice_id' => $replacement->id, 'action' => 'transferred', 'claim_status_after' => 'paid']);
    }

    public function test_manual_invoice_for_a_paid_or_overlapping_period_is_rejected(): void
    {
        $this->invoice->update(['billing_period_start' => '2027-01-01', 'billing_period_end' => '2027-12-31']);
        $this->record(100);
        foreach (['2027-01-01', '2027-06-01'] as $start) {
            $this->postJson("/api/v1/clubs/{$this->club->id}/members/{$this->member->id}/invoices", [
                'title' => 'Beitrag', 'amount' => 100, 'due_date' => '2027-12-31',
                'billing_period_start' => $start, 'billing_period_end' => '2027-12-31',
            ])->assertUnprocessable()->assertJsonValidationErrors('billing_period_start');
        }
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_new_invoice_consumes_existing_credit_without_a_second_cash_receipt(): void
    {
        $this->record();
        $this->cancel();
        $response = $this->postJson("/api/v1/clubs/{$this->club->id}/members/{$this->member->id}/invoices", [
            'title' => 'Beitrag', 'amount' => 100, 'due_date' => now()->addDays(7)->toDateString(),
        ])->assertCreated()->assertJsonPath('data.invoices.0.outstanding_amount', '80.00')
            ->assertJsonPath('data.invoices.0.payments.0.purpose', 'credit_allocation')
            ->assertJsonPath('data.summary.cash_balance', 20);
        $this->assertDatabaseCount('payments', 1);
        $replacement = Invoice::findOrFail($response->json('data.invoices.0.id'));
        $this->record(80, $replacement);
        $this->assertSame(10000, $replacement->fresh()->receivedCents());
        $this->assertSame(100.0, (float) Payment::where('status', 'paid')->sum('amount'));
    }

    public function test_credit_can_be_split_reused_after_cancellation_and_not_double_spent(): void
    {
        $this->record(100);
        $this->cancel();
        $first = $this->newInvoice(['amount' => 30]);
        $second = $this->newInvoice(['amount' => 90]);
        $this->applyCredit($first);
        $this->applyCredit($first);
        $this->applyCredit($second);
        $this->assertSame(3000, $first->fresh()->receivedCents());
        $this->assertSame(7000, $second->fresh()->receivedCents());
        $this->assertSame(2000, $second->fresh()->outstandingCents());
        $this->cancel('payment_error', $first);
        $this->assertSame('paid', Payment::first()->status);
        $third = $this->newInvoice(['amount' => 40]);
        $this->applyCredit($third);
        $this->assertSame(3000, $third->fresh()->receivedCents());
        $this->assertSame(10000, (int) ClubPaymentAllocation::whereNull('released_at')->sum('amount_cents'));
        $this->assertSame(100.0, (float) Payment::where('status', 'paid')->sum('amount'));
    }

    public function test_credit_does_not_cross_clubs_or_members_with_a_shared_payer(): void
    {
        $child = User::factory()->create();
        $this->record();
        $this->cancel();
        $other = $this->newInvoice(['membership_user_id' => $child->id]);
        $this->applyCredit($other);
        $this->assertSame(0, $other->receivedCents());
        $foreign = Club::factory()->create(['owner_id' => $this->owner->id]);
        $foreignInvoice = $this->newInvoice(['club_id' => $foreign->id]);
        $this->applyCredit($foreignInvoice);
        $this->assertSame(0, $foreignInvoice->receivedCents());
    }

    public function test_correction_cannot_overpay_or_edit_an_allocated_credit(): void
    {
        $payment = $this->record();
        $this->putJson("/api/v1/clubs/{$this->club->id}/payments/{$payment->id}", ['amount' => 150, 'method' => 'cash'])
            ->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->assertSame('20.00', $payment->fresh()->amount);
        $this->cancel();
        $this->applyCredit($this->newInvoice());
        $this->putJson("/api/v1/clubs/{$this->club->id}/payments/{$payment->id}", ['amount' => 10, 'method' => 'cash'])->assertUnprocessable();
    }

    public function test_waiver_preserves_original_amount_payment_and_waives_only_remaining_balance(): void
    {
        $this->record();
        $this->putJson($this->statusUrl(), ['status' => 'waived'])->assertOk()
            ->assertJsonPath('data.invoices.0.amount', '100.00')
            ->assertJsonPath('data.invoices.0.received_amount', '20.00')
            ->assertJsonPath('data.invoices.0.waived_amount', '80.00')
            ->assertJsonPath('data.invoices.0.overpaid_amount', '0.00')
            ->assertJsonPath('data.invoices.0.outstanding_amount', '0.00')
            ->assertJsonPath('data.invoices.0.is_partially_paid', false);
        $this->assertSame('waived', $this->invoice->fresh()->claim_status);
        $this->putJson($this->statusUrl(), ['status' => 'open'])->assertUnprocessable();
    }

    public function test_cancelled_invoice_retains_historical_payment_and_immutable_booking_chain(): void
    {
        $payment = $this->record();
        $originalReceipt = PaymentBookingReceipt::firstOrFail();
        $response = $this->putJson($this->statusUrl(), ['status' => 'cancelled', 'cancellation_action' => 'credit'])->assertOk()
            ->assertJsonCount(1, 'data.invoices.0.payments')
            ->assertJsonPath('data.invoices.0.payments.0.status', 'credited')
            ->assertJsonPath('data.invoices.0.payments.0.counts_toward_balance', false)
            ->assertJsonPath('data.invoices.0.payments.0.amount', '20.00');
        $receipt = PaymentBookingReceipt::latest('id')->firstOrFail();
        $this->assertSame($this->invoice->id, $receipt->invoice_id);
        $this->assertSame($originalReceipt->hash, $receipt->previous_hash);
        $this->assertSame('credited', $receipt->action);
        $this->assertSame($originalReceipt->hash, $originalReceipt->fresh()->hash);
        $this->assertNull($payment->fresh()->invoice_id);
    }

    public function test_payment_error_is_not_a_received_payment_and_cannot_be_corrected(): void
    {
        $payment = $this->record();
        $this->putJson($this->statusUrl(), ['status' => 'cancelled', 'cancellation_action' => 'payment_error'])->assertOk()
            ->assertJsonPath('data.invoices.0.received_amount', '0.00')
            ->assertJsonPath('data.invoices.0.payments.0.status', 'cancelled')
            ->assertJsonPath('data.summary.cash_balance', 0);
        $this->assertDatabaseHas('payment_booking_receipts', ['payment_id' => $payment->id, 'action' => 'cancelled']);
        $this->putJson("/api/v1/clubs/{$this->club->id}/payments/{$payment->id}", ['amount' => 20, 'method' => 'cash'])->assertUnprocessable();
    }

    public function test_member_balances_include_external_members_and_older_than_sixty_invoices(): void
    {
        $external = ClubExternalMember::create(['club_id' => $this->club->id, 'name' => 'External', 'email' => 'external@example.test', 'membership_status' => 'active']);
        $externalInvoice = $this->newInvoice(['user_id' => null, 'membership_user_id' => null, 'club_external_member_id' => $external->id]);
        $this->record(20, $externalInvoice);
        for ($i = 0; $i < 61; $i++) {
            $this->newInvoice(['status' => 'cancelled', 'claim_status' => 'cancelled']);
        }
        $balances = Invoice::memberOpenBalances($this->club->id);
        $this->assertSame('80.00', $balances['external:'.$external->id]);
        $this->assertSame('100.00', $balances['member:'.$this->member->id]);
        $this->getJson("/api/v1/clubs/{$this->club->id}")->assertOk()
            ->assertJsonPath('data.management.summary.member_open_balances.external:'.$external->id, '80.00')
            ->assertJsonPath('data.management.summary.member_open_balances.member:'.$this->member->id, '100.00');
    }

    public function test_legacy_cancellation_credit_keeps_its_history_and_is_used_once(): void
    {
        $payment = Payment::create([
            'club_id' => $this->club->id, 'user_id' => $this->member->id,
            'purpose' => 'prepayment', 'status' => 'paid', 'method' => 'cash', 'amount' => 20, 'paid_at' => now(),
        ]);
        $this->invoice->update(['status' => 'cancelled', 'claim_status' => 'cancelled',
            'contribution_snapshot' => ['cancellation' => ['action' => 'credit', 'payment_ids' => [$payment->id]]]]);
        $this->assertSame('20.00', $this->invoice->fresh()->paymentHistoryPayload()[0]['amount']);
        $next = $this->newInvoice();
        $this->applyCredit($next);
        $this->applyCredit($next);
        $this->assertSame(2000, $next->fresh()->receivedCents());
        $this->assertDatabaseCount('club_payment_allocations', 1);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_pending_payment_is_cancelled_with_invoice_without_inventing_cash(): void
    {
        $payment = app(ClubInvoicePaymentService::class)->record($this->invoice, [
            'amount' => 100, 'method' => 'bank_transfer', 'status' => 'pending',
        ], $this->owner);
        $this->cancel();
        $this->assertSame('cancelled', $payment->fresh()->status);
        $this->assertSame(0, $this->invoice->fresh()->receivedCents());
        $this->assertDatabaseHas('payment_booking_receipts', ['payment_id' => $payment->id, 'action' => 'cancelled']);
    }

    public function test_recurring_run_skips_an_existing_manual_period_in_its_preview_and_creation(): void
    {
        $this->club->users()->updateExistingPivot($this->member->id, [
            'contribution_amount' => 100, 'contribution_interval' => 'yearly',
            'contribution_next_invoice_on' => '2026-01-01', 'joined_on' => '2026-01-01',
            'payment_method' => 'bank_transfer',
        ]);
        $this->invoice->update(['billing_period_start' => '2026-05-01', 'billing_period_end' => '2026-12-31']);
        $service = app(ClubContributionInvoiceRunService::class);
        $preview = $service->preview($this->club, '2026-10-09');
        $this->assertSame(0, $preview['billable_count']);
        $this->assertSame('duplicate', $preview['rows']->first()['skip_reason']);
        $service->create($this->club, $this->owner, '2026-10-09');
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_mixed_payment_and_credit_cancellation_only_reverses_new_cash(): void
    {
        $this->record();
        $this->cancel();
        $next = $this->newInvoice();
        $this->applyCredit($next);
        $this->record(10, $next);
        $this->cancel('payment_error', $next);
        $this->assertSame(20.0, (float) Payment::where('status', 'paid')->sum('amount'));
        $third = $this->newInvoice();
        $this->applyCredit($third);
        $this->assertSame(2000, $third->fresh()->receivedCents());
        $this->assertSame(8000, $third->fresh()->outstandingCents());
    }

    public function test_cancellation_replacement_preserves_a_previously_waived_remainder(): void
    {
        $this->record();
        $this->putJson($this->statusUrl(), ['status' => 'waived'])->assertOk();
        $this->cancel();
        $replacement = app(ClubInvoiceCancellationService::class)->replaceAccidentalCancellation($this->invoice, $this->owner);
        $this->assertSame('waived', $replacement->status);
        $this->assertSame('100.00', $replacement->amount);
        $this->assertSame(2000, $replacement->receivedCents());
        $this->assertSame('80.00', $replacement->balancePayload()['waived_amount']);
        $this->assertSame(0, $replacement->outstandingCents());
    }

    public function test_missing_credit_migration_preserves_reading_but_blocks_spending_credit(): void
    {
        $this->record();
        $this->cancel();
        Schema::drop('club_payment_allocations');
        $this->getJson("/api/v1/clubs/{$this->club->id}/billing")->assertOk();
        $this->postJson("/api/v1/clubs/{$this->club->id}/members/{$this->member->id}/invoices", [
            'title' => 'Beitrag', 'amount' => 100, 'due_date' => now()->addDays(7)->toDateString(),
        ])->assertUnprocessable();
        $this->assertDatabaseCount('invoices', 1);
        $this->assertSame(20.0, (float) Payment::where('status', 'paid')->sum('amount'));
    }

    public function test_cancellation_rolls_back_payment_changes_if_booking_receipt_fails(): void
    {
        $payment = $this->record();
        PaymentBookingReceipt::creating(fn () => throw new \RuntimeException('Receipt unavailable'));
        try {
            $this->cancel();
            $this->fail('Cancellation must roll back when its receipt cannot be written.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Receipt unavailable', $error->getMessage());
        } finally {
            Event::forget('eloquent.creating: '.PaymentBookingReceipt::class);
        }
        $this->assertSame($this->invoice->id, $payment->fresh()->invoice_id);
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('open', $this->invoice->fresh()->status);
        $this->assertSame(2000, $this->invoice->fresh()->receivedCents());
        $this->assertDatabaseCount('payment_booking_receipts', 1);
    }

    public function test_credit_application_rolls_back_allocation_and_invoice_status_if_receipt_fails(): void
    {
        $this->record();
        $this->cancel();
        $next = $this->newInvoice(['amount' => 20]);
        PaymentBookingReceipt::creating(fn () => throw new \RuntimeException('Receipt unavailable'));
        try {
            $this->applyCredit($next);
            $this->fail('Credit application must roll back when its receipt cannot be written.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Receipt unavailable', $error->getMessage());
        } finally {
            Event::forget('eloquent.creating: '.PaymentBookingReceipt::class);
        }
        $this->assertDatabaseCount('club_payment_allocations', 0);
        $this->assertSame('open', $next->fresh()->status);
        $this->assertSame(2000, $next->fresh()->outstandingCents());
        $this->assertDatabaseCount('payments', 1);
    }
}
