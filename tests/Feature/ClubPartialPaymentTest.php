<?php

namespace Tests\Feature;

use App\Models\BankTransaction;
use App\Models\Club;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\ClubInvoiceCreated;
use App\Services\ClubInvoicePaymentService;
use App\Services\ClubMembershipBankReconciliationService;
use App\Support\BillingOverview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ClubPartialPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Club $club;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->owner = User::factory()->create();
        $member = User::factory()->create();
        $this->club = Club::factory()->create(['owner_id' => $this->owner->id]);
        $this->club->users()->attach($member->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
        ]);
        $plan = SubscriptionPlan::query()->firstOrCreate(['slug' => 'pro'], ['target_actor' => 'verein', 'name' => 'Club',
            'monthly_price_cents' => 2990, 'yearly_price_cents' => 29900,
            'currency' => 'EUR', 'features' => [], 'is_active' => true,
        ]);
        $this->club->currentSubscription()->updateOrCreate([], [
            'subscription_plan_id' => $plan->id, 'status' => 'active', 'billing_interval' => 'monthly',
        ]);
        $this->invoice = Invoice::query()->create([
            'club_id' => $this->club->id, 'user_id' => $member->id,
            'number' => 'PART-100', 'title' => 'Beitrag', 'amount' => '100.00',
            'status' => 'open', 'due_date' => now()->addDays(7), 'issued_at' => now(),
        ]);
        Sanctum::actingAs($this->owner);
    }

    private function url(): string
    {
        return "/api/v1/clubs/{$this->club->id}/membership-invoices/{$this->invoice->id}/payments";
    }

    public function test_partial_receipt_remains_open_and_default_final_payment_only_settles_the_balance(): void
    {
        $this->postJson($this->url(), ['amount' => 20, 'method' => 'cash'])
            ->assertOk()->assertJsonPath('data.invoices.0.status', 'open')
            ->assertJsonPath('data.invoices.0.received_amount', '20.00')
            ->assertJsonPath('data.invoices.0.outstanding_amount', '80.00')
            ->assertJsonPath('data.invoices.0.is_partially_paid', true)
            ->assertJsonPath('data.invoice_summary.open_amount', 80)
            ->assertJsonPath('data.invoice_summary.paid_amount', 20);
        $this->assertNull($this->invoice->fresh()->paid_at);
        $this->getJson("/api/v1/clubs/{$this->club->id}/billing")->assertOk()
            ->assertJsonPath('data.invoices.data.0.outstanding_amount', '80.00');
        $this->postJson($this->url(), ['method' => 'bank_transfer'])->assertOk()
            ->assertJsonPath('data.invoices.0.status', 'paid')
            ->assertJsonPath('data.invoices.0.outstanding_amount', '0.00')
            ->assertJsonPath('data.invoice_summary.paid_amount', 100);
        $this->assertSame(['20.00', '80.00'], $this->invoice->payments()->orderBy('id')->pluck('amount')->all());
        $this->postJson($this->url(), ['amount' => 80])->assertUnprocessable();
        $this->assertSame(2, $this->invoice->payments()->count());
    }

    public function test_web_payment_and_member_portal_share_the_same_remaining_balance(): void
    {
        $this->actingAs($this->owner)->post(route('auth.club-memberships.invoices.payments.store', $this->invoice), [
            'amount' => '30.25', 'method' => 'cash',
        ])->assertRedirect();
        $this->assertSame('open', $this->invoice->fresh()->status);
        $this->assertSame(6975, $this->invoice->fresh()->outstandingCents());
        Sanctum::actingAs($this->invoice->user);
        $this->getJson('/api/v1/billing/invoices')->assertOk()
            ->assertJsonPath('meta.summary.open_amount', 69.75);
        $this->getJson('/api/v1/settings')->assertOk()
            ->assertJsonPath('data.billing_history.invoices.0.outstanding_amount', '69.75');
    }

    public function test_overpayment_is_retained_and_exposed_separately(): void
    {
        $this->postJson($this->url(), ['amount' => '120.00'])->assertOk()
            ->assertJsonPath('data.invoices.0.status', 'paid')
            ->assertJsonPath('data.invoices.0.received_amount', '120.00')
            ->assertJsonPath('data.invoices.0.overpaid_amount', '20.00')
            ->assertJsonPath('data.invoices.0.outstanding_amount', '0.00');
        $this->assertSame('120.00', $this->invoice->payments()->firstOrFail()->amount);
    }

    public function test_payment_correction_reopens_invoice_and_records_before_and_after(): void
    {
        $this->postJson($this->url(), ['amount' => 100])->assertOk();
        $payment = $this->invoice->payments()->firstOrFail();
        $this->putJson("/api/v1/clubs/{$this->club->id}/payments/{$payment->id}", ['amount' => 40])
            ->assertOk()->assertJsonPath('data.invoices.0.status', 'open')
            ->assertJsonPath('data.invoices.0.outstanding_amount', '60.00');
        $this->assertNull($this->invoice->fresh()->paid_at);
        $this->assertDatabaseHas('activities', [
            'club_id' => $this->club->id, 'type' => 'club.payment.corrected', 'subject_id' => $payment->id,
        ]);
    }

    public function test_cancelled_invoice_rejects_payment_without_any_write(): void
    {
        $this->invoice->update(['status' => 'cancelled']);
        $this->postJson($this->url(), ['amount' => 20])->assertUnprocessable();
        $this->assertSame(0, $this->invoice->payments()->count());
    }

    public function test_paid_invoice_cancellation_requires_a_decision_and_keeps_cash_as_member_credit(): void
    {
        $this->postJson($this->url(), ['amount' => 100, 'method' => 'cash'])->assertOk();
        $statusUrl = "/api/v1/clubs/{$this->club->id}/membership-invoices/{$this->invoice->id}/status";

        $this->putJson($statusUrl, ['status' => 'cancelled'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cancellation_action');

        $this->putJson($statusUrl, [
            'status' => 'cancelled',
            'cancellation_action' => 'credit',
        ])->assertOk()
            ->assertJsonPath('data.invoices.0.status', 'cancelled')
            ->assertJsonPath('data.summary.cash_balance', 100);

        $payment = Payment::query()->firstOrFail();
        $this->assertNull($payment->invoice_id);
        $this->assertSame('prepayment', $payment->purpose);
        $this->assertSame('paid', $payment->status);
        $this->assertSame('cancelled', $this->invoice->fresh()->status);
        $this->assertDatabaseHas('activities', [
            'club_id' => $this->club->id,
            'type' => 'club.invoice.cancelled',
            'subject_id' => $this->invoice->id,
        ]);
    }

    public function test_manual_payment_error_removes_false_cash_but_bank_linked_payment_cannot_be_reversed(): void
    {
        $this->postJson($this->url(), ['amount' => 100, 'method' => 'cash'])->assertOk();
        $payment = Payment::query()->firstOrFail();
        $statusUrl = "/api/v1/clubs/{$this->club->id}/membership-invoices/{$this->invoice->id}/status";

        BankTransaction::query()->create([
            'club_id' => $this->club->id,
            'invoice_id' => $this->invoice->id,
            'payment_id' => $payment->id,
            'transaction_hash' => hash('sha256', 'linked-payment'),
            'amount' => 100,
            'currency' => 'EUR',
            'status' => 'booked',
        ]);

        $this->putJson($statusUrl, [
            'status' => 'cancelled',
            'cancellation_action' => 'payment_error',
        ])->assertUnprocessable()->assertJsonValidationErrors('cancellation_action');

        BankTransaction::query()->delete();
        $this->putJson($statusUrl, [
            'status' => 'cancelled',
            'cancellation_action' => 'payment_error',
        ])->assertOk()->assertJsonPath('data.summary.cash_balance', 0);

        $this->assertSame('cancelled', $payment->fresh()->status);
        $this->assertSame($this->invoice->id, $payment->fresh()->invoice_id);
    }

    public function test_accidental_cancellation_creates_one_replacement_transfers_credit_and_notifies_member(): void
    {
        $this->postJson($this->url(), ['amount' => 100, 'method' => 'cash'])->assertOk();
        $statusUrl = "/api/v1/clubs/{$this->club->id}/membership-invoices/{$this->invoice->id}/status";
        $this->putJson($statusUrl, [
            'status' => 'cancelled',
            'cancellation_action' => 'credit',
        ])->assertOk();

        $replacementUrl = "/api/v1/clubs/{$this->club->id}/membership-invoices/{$this->invoice->id}/replacement";
        foreach (range(1, 2) as $attempt) {
            $this->postJson($replacementUrl)->assertOk();
        }

        $this->assertSame(2, Invoice::query()->where('club_id', $this->club->id)->count());
        $replacement = Invoice::query()->whereKeyNot($this->invoice->id)->firstOrFail();
        $this->assertSame('replacement_invoice', $replacement->source);
        $this->assertSame('paid', $replacement->status);
        $this->assertSame($replacement->id, Payment::query()->firstOrFail()->invoice_id);
        $this->assertSame('membership_invoice', Payment::query()->firstOrFail()->purpose);
        $this->assertSame($replacement->id, $this->invoice->fresh()->contribution_snapshot['replacement_invoice_id']);
        Notification::assertSentTo($this->invoice->user, ClubInvoiceCreated::class);
    }

    public function test_accidental_cancellation_repairs_legacy_cancelled_invoice_with_attached_payment(): void
    {
        $this->postJson($this->url(), ['amount' => 100, 'method' => 'cash'])->assertOk();
        $this->invoice->forceFill(['status' => 'cancelled', 'claim_status' => 'cancelled'])->save();

        $this->postJson("/api/v1/clubs/{$this->club->id}/membership-invoices/{$this->invoice->id}/replacement")
            ->assertOk();

        $replacement = Invoice::query()->whereKeyNot($this->invoice->id)->firstOrFail();
        $this->assertSame('paid', $replacement->status);
        $this->assertSame($replacement->id, Payment::query()->firstOrFail()->invoice_id);
        $this->assertSame('cancelled', $this->invoice->fresh()->status);
    }

    public function test_fractional_cents_are_rejected_and_decimal_installments_settle_exactly(): void
    {
        $this->postJson($this->url(), ['amount' => '0.001'])->assertUnprocessable();
        $this->invoice->update(['amount' => '0.30']);
        $this->postJson($this->url(), ['amount' => '0.10'])->assertOk()
            ->assertJsonPath('data.invoices.0.outstanding_amount', '0.20');
        $this->postJson($this->url(), ['amount' => '0.20'])->assertOk()
            ->assertJsonPath('data.invoices.0.status', 'paid');
    }

    public function test_overdue_partial_invoice_keeps_overdue_state_and_remaining_amount(): void
    {
        $this->invoice->update(['status' => 'overdue', 'due_date' => now()->subDay()]);
        $this->postJson($this->url(), ['amount' => 25])->assertOk()
            ->assertJsonPath('data.invoices.0.status', 'overdue')
            ->assertJsonPath('data.invoice_summary.overdue_amount', 75);
    }

    public function test_foreign_club_and_regular_member_cannot_record_or_correct_payments(): void
    {
        $this->postJson($this->url(), ['amount' => 20])->assertOk();
        $payment = $this->invoice->payments()->firstOrFail();
        Sanctum::actingAs($this->invoice->user);
        $this->postJson($this->url(), ['amount' => 20])->assertForbidden();
        $this->putJson("/api/v1/clubs/{$this->club->id}/payments/{$payment->id}", ['amount' => 1])->assertForbidden();
        Sanctum::actingAs($this->owner);
        $other = Club::factory()->create(['owner_id' => $this->owner->id]);
        $this->postJson("/api/v1/clubs/{$other->id}/membership-invoices/{$this->invoice->id}/payments", ['amount' => 20])->assertNotFound();
        $this->assertSame('20.00', $payment->fresh()->amount);
    }

    public function test_failed_payments_do_not_reduce_the_balance_and_legacy_paid_invoices_remain_settled(): void
    {
        Payment::query()->create([
            'club_id' => $this->club->id, 'invoice_id' => $this->invoice->id, 'user_id' => $this->invoice->user_id,
            'amount' => 40, 'status' => 'failed',
        ]);
        $this->assertSame(10000, $this->invoice->outstandingCents());
        $this->invoice->update(['status' => 'paid']);
        $this->assertSame(0, $this->invoice->outstandingCents());
        $this->assertSame(100.0, BillingOverview::clubInvoiceSummary([$this->invoice])['paid_amount']);
    }

    public function test_bank_match_uses_outstanding_amount_and_rejects_repeated_settlement(): void
    {
        $this->postJson($this->url(), ['amount' => 20])->assertOk();
        $bank = app(ClubMembershipBankReconciliationService::class);
        $transaction = ['amount' => 80, 'purpose' => 'PART-100', 'debtor_iban' => null];
        $match = $bank->matchInvoice($this->club, $transaction);
        $this->assertSame('matched', $match['status']);
        $bank->recordMatchedPayment($match['invoice'], $transaction);
        $this->assertSame('paid', $this->invoice->fresh()->status);
        $this->assertSame('unmatched', $bank->matchInvoice($this->club, $transaction)['status']);
        $this->assertSame(2, $this->invoice->payments()->count());
    }

    public function test_bank_import_settles_remaining_balance_and_duplicate_import_does_not_add_payments(): void
    {
        $this->postJson($this->url(), ['amount' => 20])->assertOk();
        $csv = "Datum;Betrag;Währung;Auftraggeber;IBAN;Verwendungszweck\n23.09.2026;80,00;EUR;Mitglied;;PART-100";
        $this->post("/api/v1/clubs/{$this->club->id}/bank-transactions/preview", [
            'file' => UploadedFile::fake()->createWithContent('remaining.csv', $csv),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.stats.matched', 1);
        foreach (range(1, 2) as $attempt) {
            $this->post("/api/v1/clubs/{$this->club->id}/bank-transactions/import", [
                'file' => UploadedFile::fake()->createWithContent('remaining.csv', $csv),
            ], ['Accept' => 'application/json'])->assertCreated();
        }
        $this->assertSame('paid', $this->invoice->fresh()->status);
        $this->assertSame(['20.00', '80.00'], $this->invoice->payments()->orderBy('id')->pluck('amount')->all());
        $this->assertDatabaseCount('bank_transactions', 1);
    }

    public function test_payment_status_is_rechecked_when_given_a_stale_invoice_instance(): void
    {
        $stale = $this->invoice->fresh();
        $this->postJson($this->url(), ['amount' => 100])->assertOk();
        try {
            app(ClubInvoicePaymentService::class)->record($stale, ['amount' => 100], $this->owner);
            $this->fail('A stale open invoice must not allow a second settlement.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->assertSame(1, $this->invoice->payments()->count());
    }

    public function test_sepa_xml_debits_only_the_outstanding_amount(): void
    {
        $this->club->update(['sepa_creditor_id' => 'DE98ZZZ09999999999', 'sepa_iban' => 'DE02120300000000202051']);
        $this->club->users()->updateExistingPivot($this->invoice->user_id, [
            'sepa_iban' => 'DE12500105170648489890', 'sepa_mandate_reference' => 'MANDAT-PART',
            'sepa_mandate_signed_on' => now()->subMonth()->toDateString(), 'sepa_mandate_active' => true,
        ]);
        $this->postJson($this->url(), ['amount' => 20])->assertOk();
        $this->get("/api/v1/clubs/{$this->club->id}/membership/sepa-export")
            ->assertOk()->assertSee('<InstdAmt Ccy="EUR">80.00</InstdAmt>', false)
            ->assertSee('<CtrlSum>80.00</CtrlSum>', false);
    }
}
