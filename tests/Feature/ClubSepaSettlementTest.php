<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\ClubController;
use App\Http\Controllers\ClubMembershipController;
use App\Http\Controllers\PaymentController;
use App\Models\Activity;
use App\Models\BankTransaction;
use App\Models\Club;
use App\Models\ClubFinanceEntry;
use App\Models\ClubSepaBatch;
use App\Models\ClubSepaBatchItem;
use App\Models\ClubSepaFeeCorrection;
use App\Models\ClubSepaFeeRecharge;
use App\Models\ClubSepaFeeRechargeCredit;
use App\Models\ClubSepaFeeRechargeVoid;
use App\Models\ClubSepaSettlement;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\ClubInvoicePaymentService;
use App\Services\ClubMembershipBankReconciliationService;
use App\Services\ClubSepaFeeRechargeService;
use App\Services\ClubSepaFeeService;
use App\Services\ClubSepaSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ClubSepaSettlementTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $owner;

    private User $reviewer;

    private Invoice $invoice;

    private ClubSepaBatch $batch;

    private ClubSepaBatchItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 9, 23)->setTime(12, 0));
        $this->owner = User::factory()->create();
        $this->reviewer = User::factory()->create();
        $member = User::factory()->create();
        $this->club = Club::factory()->create(['owner_id' => $this->owner->id, 'sepa_creditor_id' => 'DE98ZZZ09999999999', 'sepa_iban' => 'DE02120300000000202051']);
        $this->club->users()->attach($this->reviewer->id, ['role' => 'financial_controller', 'roles' => ['financial_controller']]);
        $this->club->users()->attach($member->id, ['role' => 'member', 'roles' => ['member'], 'sepa_iban' => 'DE12500105170648489890', 'sepa_mandate_reference' => 'MANDATE-1', 'sepa_mandate_signed_on' => '2026-09-01', 'sepa_mandate_active' => true]);
        $plan = SubscriptionPlan::firstOrCreate(['slug' => 'pro'], ['name' => 'Pro', 'target_actor' => 'verein', 'is_active' => true]);
        $this->club->currentSubscription()->updateOrCreate([], ['subscription_plan_id' => $plan->id, 'status' => 'active']);
        $this->invoice = Invoice::create(['club_id' => $this->club->id, 'user_id' => $member->id, 'number' => 'R-1', 'title' => 'Beitrag', 'amount' => 100, 'status' => 'open', 'due_date' => '2026-10-10']);
        app(ClubInvoicePaymentService::class)->record($this->invoice, ['amount' => 20, 'method' => 'cash'], $this->owner);
        Sanctum::actingAs($this->owner);
        $id = $this->postJson($this->base(), ['invoice_ids' => [$this->invoice->id], 'collection_date' => '2026-10-10', 'notice_days' => 14])->assertCreated()->json('data.id');
        $this->batch = ClubSepaBatch::findOrFail($id);
        $this->item = $this->batch->items()->firstOrFail();
        Sanctum::actingAs($this->reviewer);
        $this->postJson($this->base()."/{$id}/approve")->assertOk();
        $this->postJson($this->base()."/{$id}/notice", ['confirmed' => true, 'sent_on' => '2026-09-23', 'channel' => 'letter', 'reference' => 'Postal proof'])->assertOk();
        $this->postJson($this->base()."/{$id}/export")->assertOk();
        $this->travelTo(now()->setDate(2026, 10, 11));
    }

    private function base(): string
    {
        return "/api/v1/clubs/{$this->club->id}/sepa-batches";
    }

    private function path(string $action): string
    {
        return $this->base()."/{$this->batch->id}/items/{$this->item->id}/{$action}";
    }

    private function receipt(array $extra = []): array
    {
        return array_replace(['confirmed' => true, 'booked_on' => '2026-10-10', 'reference' => 'BANK-CREDIT-1'], $extra);
    }

    private function returned(array $extra = []): array
    {
        return array_replace(['confirmed' => true, 'booked_on' => '2026-10-11', 'reference' => 'BANK-RETURN-1', 'reason' => 'Reason supplied by bank', 'fee_cents' => 350], $extra);
    }

    public function test_receipt_return_and_four_eyes_retry_preserve_partial_payment_and_history(): void
    {
        $xml = $this->batch->fresh()->export_xml;
        $this->postJson($this->path('settle'), $this->receipt())->assertOk()->assertJsonPath('data.status', 'settled');
        $this->assertSame('paid', $this->invoice->fresh()->status);
        $result = $this->item->settlement()->firstOrFail();
        $this->postJson($this->path('return'), $this->returned())->assertOk()->assertJsonPath('data.status', 'returned');
        $invoice = $this->invoice->fresh();
        $this->assertSame('overdue', $invoice->status);
        $this->assertNull($invoice->paid_at);
        $this->assertSame(2000, $invoice->receivedCents());
        $this->assertSame(8000, $invoice->outstandingCents());
        $this->assertSame('100.00', $invoice->amount);
        $this->assertSame('returned', $result->payment->status);
        $this->assertNotNull($result->payment->paid_at);
        $this->assertSame($invoice->id, $this->item->fresh()->reserved_invoice_id);
        $this->postJson($this->path('retry'), ['confirmed' => true, 'reason' => 'Bank cause checked'])->assertUnprocessable();
        Sanctum::actingAs($this->owner);
        $this->postJson($this->path('retry'), ['confirmed' => true, 'reason' => 'Bank cause checked'])->assertOk();
        $this->assertNull($this->item->fresh()->reserved_invoice_id);
        $next = $this->postJson($this->base(), ['invoice_ids' => [$invoice->id], 'collection_date' => '2026-10-28', 'notice_days' => 14])->assertCreated()->json('data.id');
        $this->postJson($this->path('retry'), ['confirmed' => true, 'reason' => 'Same request retried'])->assertOk();
        $this->assertDatabaseHas('club_sepa_batch_items', ['club_sepa_batch_id' => $next, 'reserved_invoice_id' => $invoice->id]);
        $this->postJson($this->base()."/{$next}/export")->assertUnprocessable();
        $this->assertSame($xml, $this->postJson($this->base()."/{$this->batch->id}/export")->assertOk()->getContent());
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_existing_bank_matched_payment_is_linked_without_a_second_credit(): void
    {
        $payment = app(ClubMembershipBankReconciliationService::class)->recordMatchedPayment($this->invoice, [
            'amount' => 80, 'booking_date' => '2026-10-10', 'purpose' => 'Bankimport R-1',
        ]);
        $this->getJson($this->base())->assertOk()->assertJsonPath('data.data.0.items.0.payment_options.0.id', $payment->id);
        $data = $this->receipt(['payment_id' => $payment->id]);
        $this->postJson($this->path('settle'), $data)->assertOk()->assertJsonPath('data.payment_id', $payment->id);
        $this->postJson($this->path('settle'), $data)->assertOk();
        $this->assertDatabaseCount('payments', 2);
        $this->postJson($this->path('return'), $this->returned())->assertOk();
        $this->assertSame(8000, $this->invoice->fresh()->outstandingCents());
    }

    public function test_return_before_credit_never_invents_a_negative_payment(): void
    {
        $this->postJson($this->path('return'), $this->returned())->assertOk()->assertJsonPath('data.payment_id', null);
        $this->postJson($this->path('return'), $this->returned())->assertOk();
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('club_sepa_settlements', 1);
        $this->assertSame(8000, $this->invoice->fresh()->outstandingCents());
        $this->postJson($this->path('settle'), $this->receipt())->assertUnprocessable();
    }

    public function test_duplicate_receipt_is_idempotent_but_conflicting_evidence_is_rejected(): void
    {
        $this->postJson($this->path('settle'), $this->receipt())->assertOk();
        $this->postJson($this->path('settle'), $this->receipt())->assertOk();
        $this->postJson($this->path('settle'), $this->receipt(['reference' => 'ANOTHER']))->assertUnprocessable();
        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseCount('club_sepa_settlements', 1);
        $this->postJson($this->path('return'), $this->returned())->assertOk();
        $this->postJson($this->path('return'), $this->returned(['reason' => 'Changed']))->assertUnprocessable();
    }

    public function test_incorrect_payment_and_dates_do_not_mutate_money(): void
    {
        $first = $this->invoice->payments()->firstOrFail();
        $this->postJson($this->path('settle'), $this->receipt(['payment_id' => $first->id]))->assertUnprocessable();
        $this->postJson($this->path('settle'), $this->receipt(['booked_on' => '2026-10-09']))->assertUnprocessable();
        $this->postJson($this->path('return'), $this->returned(['booked_on' => '2026-10-12']))->assertUnprocessable();
        $this->postJson($this->path('return'), $this->returned(['confirmed' => false]))->assertUnprocessable();
        $this->postJson($this->path('return'), $this->returned(['fee_cents' => -1]))->assertUnprocessable();
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('club_sepa_settlements', 0);
    }

    public function test_return_does_not_reopen_a_cancelled_invoice(): void
    {
        $this->postJson($this->path('settle'), $this->receipt())->assertOk();
        $this->invoice->update(['status' => 'cancelled']);
        $this->postJson($this->path('return'), $this->returned())->assertOk();
        $this->assertSame('cancelled', $this->invoice->fresh()->status);
        Sanctum::actingAs($this->owner);
        $this->postJson($this->path('retry'), ['confirmed' => true, 'reason' => 'Retry'])->assertUnprocessable();
    }

    public function test_late_bank_credit_can_record_real_overpayment_without_disturbing_other_payments(): void
    {
        app(ClubInvoicePaymentService::class)->record($this->invoice, ['amount' => 80, 'method' => 'cash'], $this->owner);
        $this->postJson($this->path('settle'), $this->receipt())->assertOk();
        $this->assertSame('80.00', $this->invoice->fresh()->balancePayload()['overpaid_amount']);
        $this->postJson($this->path('return'), $this->returned())->assertOk();
        $this->assertSame('paid', $this->invoice->fresh()->status);
        $this->assertSame(10000, $this->invoice->fresh()->receivedCents());
        Sanctum::actingAs($this->owner);
        $this->postJson($this->path('retry'), ['confirmed' => true, 'reason' => 'Retry'])->assertUnprocessable();
    }

    public function test_legacy_paid_invoice_without_ledger_is_not_silently_reopened(): void
    {
        $this->invoice->payments()->delete();
        $this->invoice->update(['status' => 'paid']);
        $this->postJson($this->path('settle'), $this->receipt())->assertUnprocessable();
        $this->assertSame('paid', $this->invoice->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_controlled_payment_cannot_be_corrected_or_deleted_through_generic_paths(): void
    {
        $this->postJson($this->path('settle'), $this->receipt())->assertOk();
        $payment = $this->item->settlement()->firstOrFail()->payment;
        foreach (['correct', 'delete'] as $action) {
            try {
                if ($action === 'correct') {
                    app(ClubInvoicePaymentService::class)->correct($payment, ['amount' => 10]);
                } else {
                    app(PaymentController::class)->destroy($payment);
                }
                $this->fail('Controlled SEPA payment must not be altered.');
            } catch (HttpException $exception) {
                $this->assertSame(422, $exception->getStatusCode());
            }
        }
        $this->assertSame('80.00', $payment->fresh()->amount);
    }

    public function test_member_read_only_and_foreign_item_access_is_rejected(): void
    {
        $member = $this->invoice->user;
        Sanctum::actingAs($member);
        $this->postJson($this->path('settle'), $this->receipt())->assertForbidden();
        $this->club->users()->updateExistingPivot($member->id, ['permission_overrides' => ['finance.view' => true]]);
        $this->getJson($this->base())->assertOk();
        $this->postJson($this->path('return'), $this->returned())->assertForbidden();
        Sanctum::actingAs($this->owner);
        $other = $this->batch->replicate();
        $other->reference = 'OTHER';
        $other->save();
        $this->postJson($this->base()."/{$other->id}/items/{$this->item->id}/settle", $this->receipt())->assertNotFound();
    }

    public function test_return_has_its_own_period_and_csv_counter_entry(): void
    {
        $this->postJson($this->path('settle'), $this->receipt())->assertOk();
        $this->travelTo(now()->setDate(2027, 1, 3));
        $this->postJson($this->path('return'), $this->returned(['booked_on' => '2027-01-03']))->assertOk();
        $this->assertSame(0.0, ClubSepaSettlement::returnedAmount($this->club->id, now()->setYear(2026)->startOfYear(), now()->setYear(2026)->endOfYear()));
        $this->assertSame(80.0, ClubSepaSettlement::returnedAmount($this->club->id, now()->startOfYear(), now()->endOfYear()));
        Sanctum::actingAs($this->owner);
        $overview = $this->getJson("/api/v1/clubs/{$this->club->id}")->assertOk();
        $overview->assertJsonPath('data.management.summary.finance_period_year', 2027);
        $this->assertSame(0.0, (float) $overview->json('data.management.summary.income_period_total'));
        $this->assertSame(80.0, (float) $overview->json('data.management.summary.expense_period_total'));
        $this->assertSame(20.0, (float) $overview->json('data.management.summary.total_balance'));
        $original = $this->get("/api/v1/clubs/{$this->club->id}/membership/datev-export?from=2026-10-01&to=2026-10-31")->assertOk()->streamedContent();
        $this->assertStringContainsString('80,00;S;EUR;', $original);
        $this->assertStringContainsString('GJ unzugeordnet', $original);
        $this->assertStringNotContainsString('80,00;H;EUR;', $original);
        $returned = $this->get("/api/v1/clubs/{$this->club->id}/membership/datev-export?from=2027-01-01&to=2027-01-31")->assertOk()->streamedContent();
        $this->assertStringContainsString('80,00;H;EUR;', $returned);
        $this->assertStringContainsString('GJ unzugeordnet', $returned);
        $this->assertStringContainsString('0301;BANK-RETURN-1;2027;', $returned);
        $this->assertStringNotContainsString('80,00;S;EUR;', $returned);
        $this->assertStringNotContainsString('3,50;', $returned);
    }

    public function test_expanding_unicode_reference_is_rejected_before_booking(): void
    {
        $this->postJson($this->path('settle'), $this->receipt(['reference' => str_repeat('ß', 100)]))
            ->assertUnprocessable()->assertJsonValidationErrors('reference');
        $this->assertDatabaseCount('club_sepa_settlements', 0);
        $this->assertDatabaseCount('payments', 1);
        $this->postJson($this->path('return'), $this->returned(['reference' => str_repeat('ß', 100)]))
            ->assertUnprocessable()->assertJsonValidationErrors('reference');
        $this->assertDatabaseCount('club_sepa_settlements', 0);
        $this->assertSame(8000, $this->invoice->fresh()->outstandingCents());
    }

    public function test_zero_bank_reference_still_exports_as_a_return(): void
    {
        $this->postJson($this->path('settle'), $this->receipt())->assertOk();
        $this->postJson($this->path('return'), $this->returned(['reference' => '0']))->assertOk();
        Sanctum::actingAs($this->owner);
        $csv = $this->get("/api/v1/clubs/{$this->club->id}/membership/datev-export?from=2026-10-01&to=2026-10-31")
            ->assertOk()->streamedContent();
        $this->assertStringContainsString('80,00;H;EUR;', $csv);
        $this->assertStringContainsString(';0;2026;', $csv);
    }

    private function returnCsv(string $rows = "R-1;2026-10-11;-80,00;EUR;BANK-RETURN-1;MS03\n"): string
    {
        return "end_to_end_id;booking_date;amount;currency;reference;reason\n".$rows;
    }

    private function returnUpload(string $contents, string $name = 'returns.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $contents);
    }

    private function importPath(string $action): string
    {
        return $this->base()."/{$this->batch->id}/returns/{$action}";
    }

    private function previewReturns(string $csv): TestResponse
    {
        return $this->postJson($this->importPath('preview'), ['file' => $this->returnUpload($csv)]);
    }

    private function importReturns(string $csv, string $token, bool $unlinked = false): TestResponse
    {
        return $this->postJson($this->importPath('import'), [
            'file' => $this->returnUpload($csv), 'preview_token' => $token, 'confirmed' => true, 'confirm_unlinked' => $unlinked,
        ]);
    }

    public function test_csv_preview_is_read_only_and_confirmed_return_is_idempotent(): void
    {
        $this->postJson($this->path('settle'), $this->receipt())->assertOk();
        $csv = $this->returnCsv();
        $preview = $this->previewReturns($csv)->assertOk()->assertJsonPath('data.can_import', true)
            ->assertJsonPath('data.rows.0.amount_cents', -8000)->assertJsonPath('data.rows.0.has_linked_receipt', true);
        $this->assertSame('paid', $this->invoice->fresh()->status);
        $this->assertSame('settled', $this->item->settlement()->first()->status);
        $token = $preview->json('data.preview_token');
        $this->importReturns($csv, $token)->assertOk()->assertJsonPath('data.imported', 1);
        $this->assertSame(8000, $this->invoice->fresh()->outstandingCents());
        $this->assertSame(2000, $this->invoice->fresh()->receivedCents());
        $this->assertDatabaseCount('payments', 2);
        $this->importReturns($csv, $token)->assertOk()->assertJsonPath('data.imported', 0)->assertJsonPath('data.already_returned', 1);
        $this->assertDatabaseCount('club_sepa_settlements', 1);
    }

    public function test_pain002_reject_preview_and_import_use_original_debit_evidence(): void
    {
        $this->postJson($this->path('settle'), $this->receipt())->assertOk();
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pain.002.001.10">
  <CstmrPmtStsRpt>
    <GrpHdr><MsgId>BANK-STATUS-1</MsgId><CreDtTm>2026-10-11T08:15:00Z</CreDtTm></GrpHdr>
    <OrgnlPmtInfAndSts>
      <TxInfAndSts>
        <OrgnlEndToEndId>R-1</OrgnlEndToEndId><TxSts>RJCT</TxSts>
        <StsRsnInf><Rsn><Cd>MS03</Cd></Rsn></StsRsnInf>
        <OrgnlTxRef><Amt><InstdAmt Ccy="EUR">80.00</InstdAmt></Amt><ReqdColltnDt>2026-10-10</ReqdColltnDt><DbtrAcct><Id><IBAN>DE12500105170648489890</IBAN></Id></DbtrAcct></OrgnlTxRef>
      </TxInfAndSts>
    </OrgnlPmtInfAndSts>
  </CstmrPmtStsRpt>
</Document>
XML;
        $columns = $this->postJson($this->importPath('columns'), ['file' => $this->returnUpload($xml, 'pain002.xml')])
            ->assertOk()->assertJsonPath('data.format', 'pain.002')->assertJsonPath('data.row_count', 1)
            ->assertJsonPath('data.columns.0', 'end_to_end_id');
        $this->assertCount(7, $columns->json('data.columns'));
        $preview = $this->postJson($this->importPath('preview'), ['file' => $this->returnUpload($xml, 'pain002.xml')])
            ->assertOk()->assertJsonPath('data.format', 'pain.002')->assertJsonPath('data.can_import', true)
            ->assertJsonPath('data.rows.0.end_to_end_id', 'R-1')->assertJsonPath('data.rows.0.amount_cents', -8000)
            ->assertJsonPath('data.rows.0.booking_date', '2026-10-10')
            ->assertJsonPath('data.rows.0.reference', 'BANK-STATUS-1:R-1')->assertJsonPath('data.rows.0.reason', 'MS03')
            ->assertJsonPath('data.rows.0.has_linked_receipt', true);
        $this->postJson($this->importPath('import'), [
            'file' => $this->returnUpload($xml, 'pain002.xml'), 'preview_token' => $preview->json('data.preview_token'), 'confirmed' => true,
        ])->assertOk()->assertJsonPath('data.imported', 1);
        $this->assertDatabaseHas('club_sepa_settlements', ['status' => 'returned', 'return_reference' => 'BANK-STATUS-1:R-1', 'return_reason' => 'MS03']);
        $this->assertSame(8000, $this->invoice->fresh()->outstandingCents());
    }

    public function test_camt_return_preview_and_import_require_a_debit_return_transaction(): void
    {
        $this->postJson($this->path('settle'), $this->receipt())->assertOk();
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Document xmlns="urn:iso:std:iso:20022:tech:xsd:camt.054.001.08">
  <BkToCstmrDbtCdtNtfctn><Ntfctn><Ntry>
    <Amt Ccy="EUR">80.00</Amt><CdtDbtInd>DBIT</CdtDbtInd><BookgDt><Dt>2026-10-11</Dt></BookgDt><NtryRef>ENTRY-1</NtryRef>
    <NtryDtls><TxDtls><Refs><EndToEndId>R-1</EndToEndId><AcctSvcrRef>BANK-CAMT-1</AcctSvcrRef></Refs>
      <AmtDtls><TxAmt><Amt Ccy="EUR">80.00</Amt></TxAmt></AmtDtls>
      <RltdPties><DbtrAcct><Id><IBAN>DE12500105170648489890</IBAN></Id></DbtrAcct></RltdPties>
      <RtrInf><Rsn><Cd>AC01</Cd></Rsn></RtrInf>
    </TxDtls></NtryDtls>
  </Ntry></Ntfctn></BkToCstmrDbtCdtNtfctn>
</Document>
XML;
        $camt053 = str_replace(
            ['camt.054.001.08', 'BkToCstmrDbtCdtNtfctn', 'Ntfctn'],
            ['camt.053.001.08', 'BkToCstmrStmt', 'Stmt'],
            $xml,
        );
        $this->postJson($this->importPath('preview'), ['file' => $this->returnUpload($camt053, 'camt053.xml')])
            ->assertOk()->assertJsonPath('data.format', 'camt.053')->assertJsonPath('data.can_import', true);
        $this->postJson($this->importPath('preview'), ['file' => $this->returnUpload(str_replace('DBIT', 'CRDT', $xml), 'credit.xml')])
            ->assertUnprocessable();
        $preview = $this->postJson($this->importPath('preview'), ['file' => $this->returnUpload($xml, 'camt054.xml')])
            ->assertOk()->assertJsonPath('data.format', 'camt.054')->assertJsonPath('data.can_import', true)
            ->assertJsonPath('data.rows.0.reference', 'BANK-CAMT-1')->assertJsonPath('data.rows.0.reason', 'AC01')
            ->assertJsonPath('data.rows.0.booking_date', '2026-10-11');
        $this->postJson($this->importPath('import'), [
            'file' => $this->returnUpload($xml, 'camt054.xml'), 'preview_token' => $preview->json('data.preview_token'), 'confirmed' => true,
        ])->assertOk()->assertJsonPath('data.imported', 1);
        $this->assertDatabaseHas('club_sepa_settlements', ['status' => 'returned', 'return_reference' => 'BANK-CAMT-1', 'return_reason' => 'AC01']);
    }

    public function test_xml_import_rejects_active_content_non_returns_and_non_iso_namespaces(): void
    {
        $active = '<?xml version="1.0"?><!DOCTYPE x [<!ENTITY probe "unsafe">]><Document xmlns="urn:iso:std:iso:20022:tech:xsd:pain.002.001.10"><CstmrPmtStsRpt/></Document>';
        $this->postJson($this->importPath('preview'), ['file' => $this->returnUpload($active, 'active.xml')])->assertUnprocessable();
        $accepted = str_replace(['<TxSts>RJCT</TxSts>', '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pain.002.001.10">'], ['<TxSts>ACTC</TxSts>', '<Document xmlns="https://example.test/not-iso">'], <<<'XML'
<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pain.002.001.10"><CstmrPmtStsRpt><GrpHdr><MsgId>X</MsgId><CreDtTm>2026-10-11T00:00:00Z</CreDtTm></GrpHdr><OrgnlPmtInfAndSts><TxInfAndSts><OrgnlEndToEndId>R-1</OrgnlEndToEndId><TxSts>RJCT</TxSts></TxInfAndSts></OrgnlPmtInfAndSts></CstmrPmtStsRpt></Document>
XML);
        $this->postJson($this->importPath('preview'), ['file' => $this->returnUpload($accepted, 'foreign.xml')])->assertUnprocessable();
        $nonReturn = str_replace('https://example.test/not-iso', 'urn:iso:std:iso:20022:tech:xsd:pain.002.001.10', $accepted);
        $this->postJson($this->importPath('preview'), ['file' => $this->returnUpload($nonReturn, 'accepted.xml')])->assertUnprocessable();
        $spoofed = '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pain.002.001.10" xmlns:foreign="https://example.test/foreign"><CstmrPmtStsRpt><GrpHdr><MsgId>X</MsgId><CreDtTm>2026-10-11T00:00:00Z</CreDtTm></GrpHdr><foreign:TxInfAndSts><foreign:OrgnlEndToEndId>R-1</foreign:OrgnlEndToEndId><foreign:TxSts>RJCT</foreign:TxSts></foreign:TxInfAndSts></CstmrPmtStsRpt></Document>';
        $this->postJson($this->importPath('preview'), ['file' => $this->returnUpload($spoofed, 'spoofed.xml')])->assertUnprocessable();
        $this->assertDatabaseCount('club_sepa_settlements', 0);
    }

    public function test_csv_uncredited_returns_need_separate_confirmation_and_do_not_reverse_other_payments(): void
    {
        $csv = $this->returnCsv();
        $preview = $this->previewReturns($csv)->assertOk()->assertJsonPath('data.unlinked_count', 1);
        $token = $preview->json('data.preview_token');
        $this->importReturns($csv, $token)->assertUnprocessable();
        $this->assertDatabaseCount('club_sepa_settlements', 0);
        $this->importReturns($csv, $token, true)->assertOk();
        $this->assertNull($this->item->settlement()->first()->payment_id);
        $this->assertSame(2000, $this->invoice->fresh()->receivedCents());
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_csv_invalid_rows_block_the_whole_import(): void
    {
        $this->postJson($this->path('settle'), $this->receipt())->assertOk();
        foreach ([
            ['R-1;2026-10-11;80;EUR;REF;MS03', 'amount_mismatch'],
            ['R-1;2026-10-11;-80;USD;REF;MS03', 'currency_mismatch'],
            ['R-1;2026-02-30;-80;EUR;REF;MS03', 'invalid_booking_date'],
            ['R-1;2026-10-12;-80;EUR;REF;MS03', 'invalid_booking_date'],
            ['R-1;2026-10-11;-80.001;EUR;REF;MS03', 'amount_mismatch'],
            ['R-1;2026-10-11;-83.50;EUR;REF;MS03', 'amount_mismatch'],
            ['R-9;2026-10-11;-80;EUR;REF;MS03', 'unknown_or_ambiguous_end_to_end_id'],
        ] as [$row, $error]) {
            $csv = $this->returnCsv($row."\n");
            $preview = $this->previewReturns($csv)->assertOk()->assertJsonPath('data.can_import', false);
            $this->assertContains($error, $preview->json('data.rows.0.errors'));
            $this->importReturns($csv, $preview->json('data.preview_token'))->assertUnprocessable();
            $this->assertSame('settled', $this->item->settlement()->first()->status);
        }
        $csv = $this->returnCsv()."R-9;2026-10-11;-80;EUR;REF;MS03\n";
        $preview = $this->previewReturns($csv)->assertOk()->assertJsonPath('data.can_import', false);
        $this->importReturns($csv, $preview->json('data.preview_token'))->assertUnprocessable();
        $this->assertSame('paid', $this->invoice->fresh()->status);
    }

    public function test_csv_proof_is_bound_to_file_actor_and_time(): void
    {
        Sanctum::actingAs($this->owner);
        $csv = $this->returnCsv();
        $token = $this->previewReturns($csv)->assertOk()->json('data.preview_token');
        $this->importReturns(str_replace('MS03', 'AM04', $csv), $token, true)->assertUnprocessable();
        $this->importReturns($csv, 'tampered-token', true)->assertUnprocessable();
        Sanctum::actingAs($this->reviewer);
        $this->importReturns($csv, $token, true)->assertUnprocessable();
        Sanctum::actingAs($this->owner);
        $this->travel(16)->minutes();
        $this->importReturns($csv, $token, true)->assertUnprocessable();
        $this->assertDatabaseCount('club_sepa_settlements', 0);
    }

    public function test_csv_changed_receipt_requires_a_fresh_preview(): void
    {
        $csv = $this->returnCsv();
        $token = $this->previewReturns($csv)->assertOk()->json('data.preview_token');
        $this->postJson($this->path('settle'), $this->receipt())->assertOk();
        $this->importReturns($csv, $token, true)->assertUnprocessable();
        $this->assertSame('paid', $this->invoice->fresh()->status);
        $fresh = $this->previewReturns($csv)->assertOk()->json('data.preview_token');
        $this->importReturns($csv, $fresh)->assertOk();
    }

    public function test_csv_duplicate_rows_and_unknown_columns_are_not_silently_ignored(): void
    {
        $row = "R-1;2026-10-11;-80;EUR;REF;MS03\n";
        $this->previewReturns($this->returnCsv($row.$row))->assertOk()->assertJsonPath('data.can_import', false)
            ->assertJsonPath('data.rows.1.errors', ['duplicate_position', 'duplicate_reference']);
        $this->previewReturns($this->returnCsv(str_repeat($row, 201)))->assertUnprocessable();
        $this->previewReturns("amount;amount\n-80;-80\n")->assertUnprocessable();
        $this->previewReturns(str_replace(";reason\n", ";reason;fee\n", $this->returnCsv()))->assertUnprocessable();
        $this->previewReturns($this->returnCsv(''))->assertUnprocessable();
        $this->assertDatabaseCount('club_sepa_settlements', 0);
    }

    public function test_csv_optional_iban_must_match_the_frozen_debtor(): void
    {
        $csv = "end_to_end_id;booking_date;amount;currency;reference;reason;iban\nR-1;2026-10-11;-80;EUR;REF;MS03;DE02120300000000202051\n";
        $preview = $this->previewReturns($csv)->assertOk()->assertJsonPath('data.can_import', false);
        $this->assertContains('iban_mismatch', $preview->json('data.rows.0.errors'));
        $this->importReturns($csv, $preview->json('data.preview_token'), true)->assertUnprocessable();
        $this->assertDatabaseCount('club_sepa_settlements', 0);
        $csv = str_replace('DE02120300000000202051', 'de12 5001 0517 0648 4898 90', $csv);
        $token = $this->previewReturns($csv)->assertOk()->assertJsonPath('data.can_import', true)->json('data.preview_token');
        $this->importReturns($csv, $token, true)->assertOk()->assertJsonPath('data.imported', 1);
    }

    public function test_csv_rejects_a_receipt_reassigned_to_another_invoice_or_club(): void
    {
        $this->postJson($this->path('settle'), $this->receipt())->assertOk();
        $payment = $this->item->settlement()->firstOrFail()->payment;
        $otherInvoice = $this->invoice->replicate();
        $otherInvoice->number = 'R-OTHER';
        $otherInvoice->save();
        $otherClub = Club::factory()->create(['owner_id' => $this->owner->id]);
        $csv = $this->returnCsv();
        foreach ([
            ['invoice_id' => $otherInvoice->id, 'club_id' => $this->club->id],
            ['invoice_id' => $this->invoice->id, 'club_id' => $otherClub->id],
        ] as $changed) {
            $payment->update($changed);
            $preview = $this->previewReturns($csv)->assertOk()->assertJsonPath('data.can_import', false);
            $this->assertContains('payment_changed', $preview->json('data.rows.0.errors'));
            $this->importReturns($csv, $preview->json('data.preview_token'))->assertUnprocessable();
            $this->assertSame('paid', $payment->fresh()->status);
            $this->assertSame('settled', $this->item->settlement()->first()->status);
        }
    }

    public function test_csv_preview_cannot_be_used_for_a_different_batch(): void
    {
        $csv = $this->returnCsv();
        $token = $this->previewReturns($csv)->assertOk()->json('data.preview_token');
        $other = $this->batch->replicate();
        $other->reference = 'OTHER-RETURN-BATCH';
        $other->save();
        $this->postJson($this->base()."/{$other->id}/returns/import", [
            'file' => $this->returnUpload($csv), 'preview_token' => $token,
            'confirmed' => true, 'confirm_unlinked' => true,
        ])->assertUnprocessable()->assertJsonPath('message', __('sepa.import_review'));
        $this->assertDatabaseCount('club_sepa_settlements', 0);
    }

    public function test_csv_access_and_confirmation_are_required(): void
    {
        $csv = $this->returnCsv();
        $token = $this->previewReturns($csv)->assertOk()->json('data.preview_token');
        $this->postJson($this->importPath('import'), ['file' => $this->returnUpload($csv), 'preview_token' => $token])->assertUnprocessable();
        Sanctum::actingAs($this->invoice->user);
        $this->previewReturns($csv)->assertForbidden();
        $this->importReturns($csv, $token, true)->assertForbidden();
        $this->assertDatabaseCount('club_sepa_settlements', 0);
    }

    public function test_fee_options_are_paginated_filtered_and_exclude_linked_or_foreign_expenses(): void
    {
        $this->postJson($this->path('return'), $this->returned())->assertOk();
        $base = ['club_id' => $this->club->id, 'type' => 'expense', 'account' => 'bank', 'title' => 'Bank fee', 'amount' => 3.5, 'booked_on' => '2026-10-11'];
        for ($i = 0; $i < 27; $i++) {
            ClubFinanceEntry::create($base + ['reference' => 'AVAILABLE-'.$i]);
        }
        $this->postJson($this->path('fee'), $this->feeData())->assertOk();
        $other = Club::factory()->create(['owner_id' => $this->owner->id]);
        foreach ([['club_id' => $other->id], ['type' => 'income'], ['account' => 'cash'], ['booked_on' => '2026-10-10'], ['booked_on' => '2026-10-12'], ['reference' => null]] as $excluded) {
            ClubFinanceEntry::create(array_replace($base + ['reference' => 'EXCLUDED'], $excluded));
        }
        $response = $this->getJson($this->path('fee-options'))->assertOk()
            ->assertJsonPath('data.total', 27)->assertJsonCount(25, 'data.data');
        $this->assertSame(['id', 'amount', 'booked_on', 'reference'], array_keys($response->json('data.data.0')));
        $this->getJson($this->path('fee-options').'?page=2')->assertOk()->assertJsonCount(2, 'data.data');
        $this->getJson($this->path('fee-options').'?q=AVAILABLE-26')->assertOk()->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.reference', 'AVAILABLE-26');
        $this->getJson($this->path('fee-options').'?q=EXCLUDED')->assertOk()->assertJsonCount(0, 'data.data');
    }

    public function test_fee_options_require_an_authorized_return_context_and_valid_query(): void
    {
        $this->getJson($this->path('fee-options'))->assertUnprocessable();
        $this->postJson($this->path('return'), $this->returned())->assertOk();
        $this->getJson($this->path('fee-options').'?page=0')->assertUnprocessable();
        $this->getJson($this->path('fee-options').'?q='.str_repeat('A', 181))->assertUnprocessable();
        $other = $this->batch->replicate();
        $other->reference = 'OTHER-FEE-CONTEXT';
        $other->save();
        $this->getJson($this->base()."/{$other->id}/items/{$this->item->id}/fee-options")->assertNotFound();
        Sanctum::actingAs($this->invoice->user);
        $this->getJson($this->path('fee-options'))->assertForbidden();
    }

    public function test_fee_export_requires_explicit_account_and_exports_only_booked_fees_in_their_period(): void
    {
        Sanctum::actingAs($this->owner);
        $this->postJson($this->path('return'), $this->returned())->assertOk();
        $url = "/api/v1/clubs/{$this->club->id}/membership/datev-export?from=2026-10-01&to=2026-10-31";
        $this->getJson($url)->assertUnprocessable(); // Informational fee is not a booking.
        $this->postJson($this->path('fee'), $this->feeData())->assertOk();
        $this->getJson($url)->assertUnprocessable()->assertJsonPath('message', __('sepa.fee_export_account_required'));
        $this->club->update(['datev_fee_account' => '1200']);
        $this->getJson($url)->assertUnprocessable();
        $this->club->update(['datev_fee_account' => '6855', 'datev_bank_account' => '1200']);
        ClubFinanceEntry::create(['club_id' => $this->club->id, 'type' => 'expense', 'account' => 'bank', 'title' => 'Other expense', 'amount' => 9, 'booked_on' => '2026-10-11', 'reference' => 'UNRELATED']);
        $csv = $this->get($url)->assertOk()->streamedContent();
        $this->assertStringContainsString('3,50;H;EUR;1200;6855;;1110;BANK-FEE-1;2026;', $csv);
        $this->assertSame(1, substr_count($csv, 'BANK-FEE-1'));
        $this->assertStringNotContainsString('UNRELATED', $csv);
        $this->assertStringNotContainsString('80,00', $csv);
        $this->getJson("/api/v1/clubs/{$this->club->id}/membership/datev-export?from=2026-11-01&to=2026-11-30")->assertUnprocessable();
        $this->assertSame(8000, $this->invoice->fresh()->outstandingCents());
    }

    public function test_fee_export_uses_expense_date_instead_of_return_date_and_includes_linked_expense_once(): void
    {
        Sanctum::actingAs($this->owner);
        $this->postJson($this->path('return'), $this->returned())->assertOk();
        $this->travelTo(now()->setDate(2027, 1, 3));
        $this->club->update(['datev_fee_account' => '6855']);
        $entry = ClubFinanceEntry::create(['club_id' => $this->club->id, 'type' => 'expense', 'account' => 'bank', 'title' => 'Fee', 'amount' => 3.5, 'booked_on' => '2027-01-03', 'reference' => 'FEE-LINKED']);
        $this->postJson($this->path('fee'), $this->feeData(['booked_on' => '2027-01-03', 'reference' => 'FEE-LINKED', 'finance_entry_id' => $entry->id]))->assertOk();
        $csv = $this->get("/api/v1/clubs/{$this->club->id}/membership/datev-export?from=2027-01-01&to=2027-01-31")->assertOk()->streamedContent();
        $this->assertStringContainsString('3,50;H;EUR;1200;6855;;0301;FEE-LINKED;2027;', $csv);
        $this->assertSame(1, substr_count($csv, 'FEE-LINKED'));
        $this->assertDatabaseCount('club_finance_entries', 1);
    }

    public function test_fee_account_settings_are_validated_and_preserved_for_older_clients(): void
    {
        Sanctum::actingAs($this->owner);
        $path = "/api/v1/clubs/{$this->club->id}/membership/datev-settings";
        $this->putJson($path, ['datev_fee_account' => '6855'])->assertOk();
        $this->assertSame('6855', $this->club->fresh()->datev_fee_account);
        $this->putJson($path, ['datev_bank_account' => '1200'])->assertOk();
        $this->assertSame('6855', $this->club->fresh()->datev_fee_account);
        $this->putJson($path, ['datev_fee_account' => '=FORMULA()'])->assertUnprocessable();
        $this->putJson($path, ['datev_fee_account' => null])->assertOk();
        $this->assertNull($this->club->fresh()->datev_fee_account);
        Sanctum::actingAs($this->invoice->user);
        $this->putJson($path, ['datev_fee_account' => '6855'])->assertForbidden();
    }

    private function correctionData(array $extra = []): array
    {
        return array_replace(['confirmed' => true, 'request_id' => (string) Str::uuid(),
            'expected_revision' => 0, 'amount_cents' => 200, 'booked_on' => '2026-10-11',
            'reference' => 'FEE-CORRECTION-1', 'reason' => 'Bank evidence corrects the recorded fee'], $extra);
    }

    public function test_fee_correction_preserves_original_and_member_debt_and_retries_only_once(): void
    {
        $this->postJson($this->path('return'), $this->returned())->assertOk();
        $this->postJson($this->path('fee'), $this->feeData())->assertOk();
        $original = $this->item->settlement()->first()->feeEntry;
        $data = $this->correctionData();
        $response = $this->postJson($this->path('fee-corrections'), $data)->assertOk()
            ->assertJsonPath('data.previous_amount_cents', 350)->assertJsonPath('data.amount_cents', 200)->assertJsonPath('data.revision', 1);
        $id = $response->json('data.id');
        $this->postJson($this->path('fee-corrections'), $data)->assertOk()->assertJsonPath('data.id', $id);
        $this->postJson($this->path('fee-corrections'), array_replace($data, ['amount_cents' => 100]))->assertUnprocessable();
        $this->postJson($this->path('fee-corrections'), $this->correctionData(['amount_cents' => 100]))->assertUnprocessable();
        $this->assertDatabaseCount('club_sepa_fee_corrections', 1);
        $this->assertDatabaseCount('club_finance_entries', 2);
        $this->assertSame('3.50', $original->fresh()->amount);
        $correctionEntry = ClubFinanceEntry::query()->whereKeyNot($original->id)->firstOrFail();
        $this->assertSame($original->id, $correctionEntry->reversal_of_id);
        $this->assertSame([
            'source' => 'club_sepa_fee_correction',
            'settlement_id' => $this->item->settlement()->first()->id,
            'revision' => 1,
            'previous_amount_cents' => 350,
            'amount_cents' => 200,
            'delta_cents' => -150,
            'previous_finance_entry_id' => null,
            'original_finance_entry_id' => $original->id,
        ], $correctionEntry->correction_snapshot);
        $this->assertSame(200, (int) round(ClubFinanceEntry::sum('amount') * 100));
        $this->assertSame(8000, $this->invoice->fresh()->outstandingCents());
        $this->assertSame(1, Activity::where('type', 'club.sepa.fee_corrected')->count());
        $this->getJson($this->base())->assertOk()->assertJsonPath('fee_corrections_available', true)
            ->assertJsonPath('data.data.0.items.0.settlement.fee_corrections.0.amount_cents', 200);
    }

    public function test_fee_correction_cancellation_exports_in_its_own_year_and_can_be_corrected_again(): void
    {
        Sanctum::actingAs($this->owner);
        $this->postJson($this->path('return'), $this->returned())->assertOk();
        $this->postJson($this->path('fee'), $this->feeData())->assertOk();
        $this->club->update(['datev_fee_account' => '6855']);
        $this->travelTo(now()->setDate(2027, 1, 3));
        $data = $this->correctionData(['booked_on' => '2027-01-03', 'amount_cents' => 0]);
        $this->postJson($this->path('fee-corrections'), $data)->assertOk();
        $csv = $this->get("/api/v1/clubs/{$this->club->id}/membership/datev-export?from=2027-01-01&to=2027-01-31")->assertOk()->streamedContent();
        $this->assertStringContainsString('3,50;S;EUR;1200;6855;;0301;FEE-CORRECTION-1;2027;', $csv);
        $this->assertStringNotContainsString('BANK-FEE-1', $csv);
        $oldCsv = $this->get("/api/v1/clubs/{$this->club->id}/membership/datev-export?from=2026-10-01&to=2026-10-31")->assertOk()->streamedContent();
        $this->assertStringContainsString('3,50;H;EUR;1200;6855;;1110;BANK-FEE-1;2026;', $oldCsv);
        $this->postJson($this->path('fee-corrections'), $this->correctionData(['expected_revision' => 1, 'amount_cents' => 100,
            'booked_on' => '2027-01-03', 'reference' => 'FEE-CORRECTION-2']))->assertOk()->assertJsonPath('data.revision', 2);
        $this->postJson($this->path('fee-corrections'), $data)->assertOk()->assertJsonPath('data.revision', 1);
        $this->assertSame(100, (int) round(ClubFinanceEntry::sum('amount') * 100));
        foreach ([ClubMembershipController::class, ClubController::class] as $controller) {
            $summary = (new \ReflectionMethod($controller, 'financeBalanceSummary'))->invoke(app($controller), $this->club);
            $this->assertEquals(1, $summary['expense_total']);
            $this->assertEquals(-2.5, $summary['expense_period_total']);
            $this->assertEquals(20, $summary['income_total']);
            $this->assertEquals(-1, $summary['bank_balance']);
        }
    }

    public function test_fee_correction_rejects_stale_invalid_unconfirmed_and_unauthorized_requests(): void
    {
        $this->postJson($this->path('return'), $this->returned())->assertOk();
        $this->postJson($this->path('fee'), $this->feeData())->assertOk();
        foreach ([['expected_revision' => 1], ['amount_cents' => 350], ['amount_cents' => -1], ['booked_on' => '2026-10-10'],
            ['booked_on' => '2026-10-12'], ['confirmed' => false], ['reason' => '   '], ['reference' => 'bank-fee-1']] as $invalid) {
            $this->postJson($this->path('fee-corrections'), $this->correctionData($invalid))->assertUnprocessable();
        }
        Sanctum::actingAs($this->invoice->user);
        $this->postJson($this->path('fee-corrections'), $this->correctionData())->assertForbidden();
        $this->assertDatabaseCount('club_sepa_fee_corrections', 0);
        $this->assertDatabaseCount('club_finance_entries', 1);
    }

    public function test_fee_correction_rolls_back_its_adjustment_when_history_creation_fails(): void
    {
        $this->postJson($this->path('return'), $this->returned())->assertOk();
        $this->postJson($this->path('fee'), $this->feeData())->assertOk();
        $event = 'eloquent.created: '.ClubSepaFeeCorrection::class;
        Event::listen($event, function () {
            throw new \RuntimeException('Simulated history failure');
        });
        try {
            app(ClubSepaFeeService::class)->correct($this->item, $this->owner, $this->correctionData());
            $this->fail('The simulated failure must escape the transaction.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated history failure', $exception->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertDatabaseCount('club_finance_entries', 1);
        $this->assertDatabaseCount('club_sepa_fee_corrections', 0);
        $this->assertSame(0, Activity::where('type', 'club.sepa.fee_corrected')->count());
        $this->assertSame(8000, $this->invoice->fresh()->outstandingCents());
    }

    public function test_fee_correction_entries_cannot_be_edited_or_linked_as_new_fees(): void
    {
        $this->postJson($this->path('return'), $this->returned())->assertOk();
        $this->postJson($this->path('fee'), $this->feeData())->assertOk();
        $id = $this->postJson($this->path('fee-corrections'), $this->correctionData(['amount_cents' => 500]))->assertOk()->json('data.finance_entry_id');
        $payload = ['type' => 'expense', 'account' => 'bank', 'title' => 'Overwrite', 'amount' => 10, 'booked_on' => '2026-10-11', 'reference' => 'OTHER'];
        $this->putJson("/api/v1/clubs/{$this->club->id}/finance-entries/{$id}", $payload)->assertUnprocessable();
        $this->actingAs($this->owner)->putJson(route('auth.club-memberships.finance-entries.update', [$this->club, $id]), $payload)->assertUnprocessable();
        $this->getJson($this->path('fee-options'))->assertOk()->assertJsonCount(0, 'data.data');
        $secondInvoice = $this->invoice->replicate();
        $secondInvoice->number = 'R-SECOND';
        $secondInvoice->save();
        $second = $this->item->replicate();
        $second->invoice_id = $secondInvoice->id;
        $second->reserved_invoice_id = null;
        $second->save();
        ClubSepaSettlement::create(['club_id' => $this->club->id,
            'club_sepa_batch_item_id' => $second->id, 'status' => 'returned', 'returned_on' => '2026-10-11', 'return_reference' => 'SECOND-RETURN']);
        $path = $this->base()."/{$this->batch->id}/items/{$second->id}/fee";
        $this->postJson($path, $this->feeData(['finance_entry_id' => $id, 'amount_cents' => 150, 'reference' => 'FEE-CORRECTION-1']))->assertUnprocessable();
        $this->assertSame('1.50', ClubFinanceEntry::findOrFail($id)->amount);
    }

    public function test_fee_recharge_endpoints_are_unavailable_before_the_additive_migration(): void
    {
        $migration = require database_path('migrations/2026_09_23_000007_create_sepa_fee_recharges.php');
        $migration->down();
        try {
            $this->getJson($this->base())->assertOk()->assertJsonPath('fee_recharge_drafts_available', false);
            $this->postJson($this->path('fee-recharges'), $this->rechargeData())->assertStatus(503);
            $this->postJson($this->path('fee-recharges/1/cancel'), ['confirmed' => true, 'reason' => 'Not installed'])->assertStatus(503);
        } finally {
            $migration->up();
        }
    }

    private function approvedRecharge(): array
    {
        $id = $this->prepareRecharge();
        Sanctum::actingAs($this->owner);
        $invoiceId = $this->postJson($this->path("fee-recharges/{$id}/approve"), $this->approvalData())->assertOk()->json('data.invoice_id');

        return [$id, Invoice::findOrFail($invoiceId), $this->path("fee-recharges/{$id}/void-requests")];
    }

    private function voidData(): array
    {
        return ['confirmed' => true, 'request_id' => (string) Str::uuid(), 'reason' => 'Documented correction of the charge'];
    }

    private function creditData(array $extra = []): array
    {
        return array_replace(['confirmed' => true, 'request_id' => (string) Str::uuid(),
            'reason' => 'Documented credit after a payment or bank process'], $extra);
    }

    public function test_fee_recharge_credit_requires_activity_and_second_person_then_preserves_payment_evidence(): void
    {
        [$id, $invoice] = $this->approvedRecharge();
        $path = $this->path("fee-recharges/{$id}/credit-requests");
        $this->postJson($path, $this->creditData())->assertUnprocessable();
        $payment = app(ClubInvoicePaymentService::class)->record($invoice, ['amount' => 0.5, 'method' => 'bank', 'reference' => 'BANK-IN-1'], $this->owner);
        $data = $this->creditData();
        $creditId = $this->postJson($path, $data)->assertOk()->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.amount_cents', 200)->json('data.id');
        $this->postJson($path, $data)->assertOk()->assertJsonPath('data.id', $creditId);
        $this->postJson($path, $this->creditData())->assertUnprocessable();
        $approve = "{$path}/{$creditId}/approve";
        $this->postJson($approve, ['confirmed' => true])->assertUnprocessable();
        Sanctum::actingAs($this->reviewer);
        $this->postJson($approve, ['confirmed' => true])->assertOk()->assertJsonPath('data.status', 'issued')
            ->assertJsonPath('data.refund_due_cents', 50)
            ->assertJsonPath('data.credit_note_number', "AIR-GS-FEE-{$this->club->id}-{$creditId}");
        $this->postJson($approve, ['confirmed' => true])->assertOk();
        $this->assertSame('cancelled', $invoice->fresh()->status);
        $this->assertSame('2.00', $invoice->fresh()->amount);
        $this->assertSame('0.50', $payment->fresh()->amount);
        $this->assertSame(8000, $this->invoice->fresh()->outstandingCents());
        $this->assertDatabaseHas('club_sepa_fee_recharges', ['id' => $id, 'status' => 'credited', 'active_settlement_id' => null]);
        $this->assertSame(1, Activity::where('type', 'club.sepa.fee_recharge_credited')->count());
        $this->getJson($this->base())->assertOk()->assertJsonPath('fee_recharge_credits_available', true)
            ->assertJsonPath('data.data.0.items.0.settlement.fee_recharges.0.credit_requests.0.refund_due_cents', 50);
        $documentPath = "{$path}/{$creditId}/document";
        $document = $this->get($documentPath)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-1.4', $document->getContent());
        $this->assertStringContainsString("AIR-GS-FEE-{$this->club->id}-{$creditId}", $document->getContent());
        $signedUrl = $this->getJson("{$documentPath}-link")->assertOk()
            ->assertJsonStructure(['data' => ['url', 'expires_at']])->json('data.url');
        $this->get($signedUrl)->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get($signedUrl.'&tampered=1')->assertForbidden();
        Sanctum::actingAs($this->invoice->user);
        $this->getJson($documentPath)->assertForbidden();
        $this->getJson("{$documentPath}-link")->assertForbidden();
        Sanctum::actingAs($this->reviewer);
        try {
            app(ClubInvoicePaymentService::class)->correct($payment, ['amount' => 0.25, 'method' => 'bank',
                'reference' => 'CHANGED', 'paid_at' => now(), 'notes' => null], $this->reviewer);
            $this->fail('Credited recharge payment evidence must be immutable');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $refund = ['confirmed' => true, 'request_id' => (string) Str::uuid(),
            'booked_on' => '2026-10-11', 'reference' => 'BANK-REFUND-1'];
        $refundPath = "{$path}/{$creditId}/refund";
        $entryId = $this->postJson($refundPath, $refund)->assertOk()->assertJsonPath('data.status', 'refunded')
            ->assertJsonPath('data.refund_created_entry', true)->json('data.refund_finance_entry_id');
        $this->postJson($refundPath, $refund)->assertOk()->assertJsonPath('data.refund_finance_entry_id', $entryId);
        $this->postJson($refundPath, array_replace($refund, ['reference' => 'CHANGED']))->assertUnprocessable();
        $entry = ClubFinanceEntry::findOrFail($entryId);
        $this->assertSame('0.50', $entry->amount);
        $this->assertSame('sepa_fee_recharge_refund', $entry->category);
        $this->assertDatabaseCount('payments', 2);
        try {
            app(ClubSepaFeeService::class)->updateFinanceEntry($entry, ['amount' => 0.25]);
            $this->fail('Documented refund finance entry must be immutable');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->assertSame(1, Activity::where('type', 'club.sepa.fee_recharge_refunded')->count());
    }

    public function test_fee_recharge_credit_can_be_withdrawn_and_replaced_without_erasing_history(): void
    {
        [$id, $invoice] = $this->approvedRecharge();
        $invoice->update(['sepa_exported_at' => now()]);
        $path = $this->path("fee-recharges/{$id}/credit-requests");
        $creditId = $this->postJson($path, $this->creditData())->assertOk()->json('data.id');
        $withdraw = "{$path}/{$creditId}/withdraw";
        $body = ['confirmed' => true, 'reason' => 'Correction request withdrawn after review'];
        $this->postJson($withdraw, $body)->assertOk()->assertJsonPath('data.status', 'withdrawn');
        $this->postJson($withdraw, $body)->assertOk();
        $this->postJson($withdraw, ['confirmed' => true, 'reason' => 'Different'])->assertUnprocessable();
        $this->postJson($path, $this->creditData())->assertOk()->assertJsonPath('data.status', 'pending');
        $this->assertDatabaseCount('club_sepa_fee_recharge_credits', 2);
        $this->assertSame('open', $invoice->fresh()->status);
    }

    public function test_fee_recharge_credit_without_received_money_issues_no_refund_obligation(): void
    {
        [$id, $invoice] = $this->approvedRecharge();
        $invoice->update(['sepa_exported_at' => now()]);
        $path = $this->path("fee-recharges/{$id}/credit-requests");
        $creditId = $this->postJson($path, $this->creditData())->assertOk()->json('data.id');
        Sanctum::actingAs($this->reviewer);
        $this->postJson("{$path}/{$creditId}/approve", ['confirmed' => true])->assertOk()
            ->assertJsonPath('data.refund_due_cents', 0)->assertJsonPath('data.status', 'completed');
        $this->postJson("{$path}/{$creditId}/approve", ['confirmed' => true])->assertOk()->assertJsonPath('data.status', 'completed');
        $this->postJson("{$path}/{$creditId}/refund", ['confirmed' => true, 'request_id' => (string) Str::uuid(),
            'booked_on' => '2026-10-11', 'reference' => 'NO-REFUND'])->assertUnprocessable();
        $this->assertSame('cancelled', $invoice->fresh()->status);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_fee_recharge_refund_links_matching_bank_expense_without_copying_it(): void
    {
        [$id, $invoice] = $this->approvedRecharge();
        app(ClubInvoicePaymentService::class)->record($invoice, ['amount' => 1], $this->owner);
        $path = $this->path("fee-recharges/{$id}/credit-requests");
        $creditId = $this->postJson($path, $this->creditData())->assertOk()->json('data.id');
        Sanctum::actingAs($this->reviewer);
        $this->postJson("{$path}/{$creditId}/approve", ['confirmed' => true])->assertOk();
        $entry = ClubFinanceEntry::create(['club_id' => $this->club->id, 'user_id' => $this->reviewer->id,
            'type' => 'expense', 'account' => 'bank', 'category' => 'bank', 'title' => 'Existing refund',
            'amount' => 1, 'booked_on' => '2026-10-11', 'reference' => 'EXISTING-REFUND']);
        $count = ClubFinanceEntry::count();
        $body = ['confirmed' => true, 'request_id' => (string) Str::uuid(), 'booked_on' => '2026-10-11',
            'reference' => 'existing-refund', 'finance_entry_id' => $entry->id];
        $this->postJson("{$path}/{$creditId}/refund", $body)->assertOk()
            ->assertJsonPath('data.refund_finance_entry_id', $entry->id)
            ->assertJsonPath('data.refund_created_entry', false);
        $this->assertSame($count, ClubFinanceEntry::count());
    }

    public function test_fee_recharge_credit_schema_gate_and_audit_failure_leave_money_unchanged(): void
    {
        [$id, $invoice] = $this->approvedRecharge();
        app(ClubInvoicePaymentService::class)->record($invoice, ['amount' => 0.5], $this->owner);
        $path = $this->path("fee-recharges/{$id}/credit-requests");
        $migration = require database_path('migrations/2026_09_24_000010_create_sepa_fee_recharge_credits.php');
        $migration->down();
        try {
            $this->getJson($this->base())->assertOk()->assertJsonPath('fee_recharge_credits_available', false);
            $this->postJson($path, $this->creditData())->assertStatus(503);
        } finally {
            $migration->up();
        }
        $creditId = $this->postJson($path, $this->creditData())->assertOk()->json('data.id');
        $event = 'eloquent.creating: '.Activity::class;
        Event::listen($event, function ($activity) {
            if ($activity->type === 'club.sepa.fee_recharge_credited') {
                throw new \RuntimeException('Credit audit failure');
            }
        });
        try {
            app(ClubSepaFeeRechargeService::class)->reviewCredit($this->item, ClubSepaFeeRecharge::findOrFail($id),
                ClubSepaFeeRechargeCredit::findOrFail($creditId), $this->reviewer, true);
            $this->fail('Expected credit audit failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Credit audit failure', $exception->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertSame('open', $invoice->fresh()->status);
        $this->assertDatabaseHas('club_sepa_fee_recharges', ['id' => $id, 'status' => 'approved']);
        $this->assertDatabaseHas('club_sepa_fee_recharge_credits', ['id' => $creditId, 'status' => 'pending']);
    }

    public function test_fee_recharge_void_requires_second_person_preserves_invoice_and_releases_reservation(): void
    {
        [$id, $invoice, $path] = $this->approvedRecharge();
        $data = $this->voidData();
        $voidId = $this->postJson($path, $data)->assertOk()->assertJsonPath('data.status', 'pending')->json('data.id');
        $this->postJson($path, $data)->assertOk()->assertJsonPath('data.id', $voidId);
        $this->postJson($path, $this->voidData())->assertUnprocessable();
        $this->postJson("{$path}/{$voidId}/approve", ['confirmed' => true])->assertUnprocessable();
        $this->assertSame('open', $invoice->fresh()->status);
        Sanctum::actingAs($this->reviewer);
        $this->postJson("{$path}/{$voidId}/approve", ['confirmed' => true])->assertOk()->assertJsonPath('data.status', 'applied');
        $this->postJson("{$path}/{$voidId}/approve", ['confirmed' => true])->assertOk();
        $this->assertSame('cancelled', $invoice->fresh()->status);
        $this->assertSame('2.00', $invoice->fresh()->amount);
        $this->assertSame(0, $invoice->fresh()->outstandingCents());
        $this->assertSame(8000, $this->invoice->fresh()->outstandingCents());
        $this->assertDatabaseHas('club_sepa_fee_recharges', ['id' => $id, 'status' => 'voided', 'active_settlement_id' => null, 'invoice_id' => $invoice->id]);
        $this->assertSame(1, Activity::where('type', 'club.sepa.fee_recharge_voided')->count());
        $this->postJson($this->path('fee-recharges'), $this->rechargeData())->assertOk();
        try {
            $invoice->fresh()->update(['status' => 'open']);
            $this->fail('Cannot reopen a controlled void');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        try {
            app(ClubInvoicePaymentService::class)->record($invoice, ['amount' => 2], $this->owner);
            $this->fail('Cannot pay a cancelled invoice');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
    }

    public function test_fee_recharge_void_rechecks_payments_at_approval_and_allows_withdrawal_without_erasing_history(): void
    {
        [, $invoice, $path] = $this->approvedRecharge();
        $voidId = $this->postJson($path, $this->voidData())->assertOk()->json('data.id');
        app(ClubInvoicePaymentService::class)->record($invoice, ['amount' => 0.5, 'method' => 'bank'], $this->owner);
        Sanctum::actingAs($this->reviewer);
        $this->postJson("{$path}/{$voidId}/approve", ['confirmed' => true])->assertUnprocessable();
        $body = ['confirmed' => true, 'reason' => 'Payment arrived; use refund review'];
        $this->postJson("{$path}/{$voidId}/withdraw", $body)->assertOk()->assertJsonPath('data.status', 'withdrawn');
        $this->postJson("{$path}/{$voidId}/withdraw", $body)->assertOk();
        $this->postJson($path, $this->voidData())->assertUnprocessable();
        $this->assertSame(150, $invoice->fresh()->outstandingCents());
        $this->assertDatabaseCount('club_sepa_fee_recharge_voids', 1);
    }

    public function test_fee_recharge_void_blocks_bank_matches_direct_exports_and_active_sepa_positions(): void
    {
        [, $invoice, $path] = $this->approvedRecharge();
        $invoice->update(['sepa_exported_at' => now()]);
        $this->postJson($path, $this->voidData())->assertUnprocessable();
        $invoice->update(['sepa_exported_at' => null]);
        $bank = BankTransaction::create(['club_id' => $this->club->id, 'invoice_id' => $invoice->id, 'transaction_hash' => 'VOID-BANK',
            'booking_date' => '2026-10-11', 'amount' => 2, 'currency' => 'EUR', 'status' => 'unmatched']);
        $this->postJson($path, $this->voidData())->assertUnprocessable();
        $bank->delete();
        $item = ClubSepaBatchItem::create(['club_sepa_batch_id' => $this->batch->id, 'invoice_id' => $invoice->id,
            'reserved_invoice_id' => $invoice->id, 'amount_cents' => 200, 'debtor_snapshot' => $this->item->debtor_snapshot]);
        $this->postJson($path, $this->voidData())->assertUnprocessable();
        $item->delete();
        $this->postJson($path, $this->voidData())->assertOk();
    }

    public function test_fee_recharge_void_withdrawal_permits_a_new_request_with_separate_history(): void
    {
        [, $invoice, $path] = $this->approvedRecharge();
        $data = $this->voidData();
        $voidId = $this->postJson($path, $data)->assertOk()->json('data.id');
        $this->postJson("{$path}/{$voidId}/withdraw", ['confirmed' => true, 'reason' => 'Review incomplete'])->assertOk();
        $this->postJson($path, $data)->assertOk()->assertJsonPath('data.status', 'withdrawn');
        $this->postJson($path, $this->voidData())->assertOk()->assertJsonPath('data.status', 'pending');
        $this->assertDatabaseCount('club_sepa_fee_recharge_voids', 2);
        $this->assertSame('open', $invoice->fresh()->status);
        $this->getJson($this->base())->assertOk()->assertJsonPath('fee_recharge_voids_available', true)
            ->assertJsonCount(2, 'data.data.0.items.0.settlement.fee_recharges.0.void_requests');
    }

    public function test_fee_recharge_void_audit_failure_rolls_back_invoice_proposal_and_review(): void
    {
        [$id, $invoice, $path] = $this->approvedRecharge();
        $voidId = $this->postJson($path, $this->voidData())->assertOk()->json('data.id');
        $event = 'eloquent.creating: '.Activity::class;
        Event::listen($event, function ($activity) {
            if ($activity->type === 'club.sepa.fee_recharge_voided') {
                throw new \RuntimeException('Void audit failure');
            }
        });
        try {
            app(ClubSepaFeeRechargeService::class)->reviewVoid($this->item, ClubSepaFeeRecharge::findOrFail($id),
                ClubSepaFeeRechargeVoid::findOrFail($voidId), $this->reviewer, true);
            $this->fail('Expected rollback');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Void audit failure', $exception->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertSame('open', $invoice->fresh()->status);
        $this->assertDatabaseHas('club_sepa_fee_recharges', ['id' => $id, 'status' => 'approved']);
        $this->assertDatabaseHas('club_sepa_fee_recharge_voids', ['id' => $voidId, 'status' => 'pending', 'reviewed_by' => null]);
    }

    public function test_fee_recharge_void_requires_rights_confirmation_and_available_schema(): void
    {
        [, , $path] = $this->approvedRecharge();
        $this->postJson($path, array_replace($this->voidData(), ['confirmed' => false]))->assertUnprocessable();
        Sanctum::actingAs($this->invoice->user);
        $this->postJson($path, $this->voidData())->assertForbidden();
        Sanctum::actingAs($this->owner);
        $migration = require database_path('migrations/2026_09_23_000009_create_sepa_fee_recharge_voids.php');
        $migration->down();
        try {
            $this->getJson($this->base())->assertOk()->assertJsonPath('fee_recharge_voids_available', false);
            $this->postJson($path, $this->voidData())->assertStatus(503);
            $this->postJson("{$path}/1/approve", ['confirmed' => true])->assertStatus(503);
        } finally {
            $migration->up();
        }
    }

    private function prepareRecharge(): int
    {
        Sanctum::actingAs($this->reviewer);
        $this->postJson($this->path('return'), $this->returned())->assertOk();
        $this->postJson($this->path('fee'), $this->feeData())->assertOk();

        return $this->postJson($this->path('fee-recharges'), $this->rechargeData())->assertOk()->json('data.id');
    }

    public function test_fee_recharge_approval_requires_its_migration_and_finance_rights(): void
    {
        $id = $this->prepareRecharge();
        $path = $this->path("fee-recharges/{$id}/approve");
        Sanctum::actingAs($this->invoice->user);
        $this->postJson($path, $this->approvalData())->assertForbidden();
        Sanctum::actingAs($this->owner);
        $migration = require database_path('migrations/2026_09_23_000008_approve_sepa_fee_recharges.php');
        $migration->down();
        try {
            $this->getJson($this->base())->assertOk()->assertJsonPath('fee_recharge_approvals_available', false);
            $this->postJson($path, $this->approvalData())->assertStatus(503);
        } finally {
            $migration->up();
        }
        $this->postJson($path, $this->approvalData())->assertOk();
    }

    private function approvalData(): array
    {
        return ['confirmed' => true, 'basis_confirmed' => true, 'revenue_account' => '4990'];
    }

    public function test_fee_recharge_approval_requires_second_person_and_creates_exactly_one_separate_invoice(): void
    {
        $id = $this->prepareRecharge();
        $path = $this->path("fee-recharges/{$id}/approve");
        $count = Invoice::count();
        $paymentsBefore = Payment::count();
        $notificationsBefore = Notification::count();
        $this->postJson($path, $this->approvalData())->assertUnprocessable();
        Sanctum::actingAs($this->owner);
        $this->postJson($path, ['confirmed' => true, 'revenue_account' => '4990'])->assertUnprocessable();
        $this->postJson($path, array_replace($this->approvalData(), ['revenue_account' => '001200']))->assertUnprocessable();
        $invoiceId = $this->postJson($path, $this->approvalData())->assertOk()->assertJsonPath('data.status', 'approved')->json('data.invoice_id');
        $this->postJson($path, $this->approvalData())->assertOk()->assertJsonPath('data.invoice_id', $invoiceId);
        $this->postJson($path, array_replace($this->approvalData(), ['revenue_account' => '4991']))->assertUnprocessable();
        $this->postJson($this->path('fee-recharges'), $this->rechargeData())->assertUnprocessable();
        $invoice = Invoice::findOrFail($invoiceId);
        $this->assertSame($count + 1, Invoice::count());
        $this->assertSame($paymentsBefore, Payment::count());
        $this->assertSame($notificationsBefore, Notification::count());
        $view = $this->getJson($this->base())->assertOk();
        $view->assertJsonPath('data.data.0.items.0.settlement.fee_recharges.0.member.name', $this->invoice->user->name)
            ->assertJsonPath('data.data.0.items.0.settlement.fee_recharges.0.invoice.number', $invoice->number)
            ->assertJsonPath('data.data.0.items.0.settlement.fee_recharges.0.invoice.status', 'open');
        $memberView = $view->json('data.data.0.items.0.settlement.fee_recharges.0.member');
        $this->assertSame(['id', 'name'], array_keys($memberView));
        $this->assertSame('sepa_fee_recharge', $invoice->source);
        $this->assertSame('2.00', $invoice->amount);
        $this->assertSame($this->invoice->user_id, $invoice->user_id);
        $this->assertSame(200, $invoice->outstandingCents());
        $this->assertSame(8000, $this->invoice->fresh()->outstandingCents());
        $this->assertSame(1, Activity::where('type', 'club.sepa.fee_recharge_approved')->count());
        $this->postJson($this->path("fee-recharges/{$id}/cancel"), ['confirmed' => true, 'reason' => 'Cannot cancel approved as draft'])->assertUnprocessable();
    }

    public function test_fee_recharge_approval_rejects_changed_fee_recipient_and_expired_date(): void
    {
        $id = $this->prepareRecharge();
        Sanctum::actingAs($this->owner);
        $path = $this->path("fee-recharges/{$id}/approve");
        $originalMember = $this->invoice->user_id;
        $this->invoice->update(['user_id' => $this->owner->id]);
        $this->postJson($path, $this->approvalData())->assertUnprocessable();
        $this->invoice->update(['user_id' => $originalMember]);
        $this->travelTo(now()->setDate(2026, 10, 21));
        $this->postJson($path, $this->approvalData())->assertUnprocessable();
        $this->travelTo(now()->setDate(2026, 10, 11));
        $this->postJson($this->path('fee-corrections'), $this->correctionData(['amount_cents' => 100]))->assertOk();
        $this->postJson($path, $this->approvalData())->assertUnprocessable();
        $this->assertSame(0, Invoice::where('source', 'sepa_fee_recharge')->count());
    }

    public function test_approved_recharge_invoice_preserves_terms_accepts_partial_payments_and_exports_its_frozen_account(): void
    {
        $id = $this->prepareRecharge();
        Sanctum::actingAs($this->owner);
        $invoiceId = $this->postJson($this->path("fee-recharges/{$id}/approve"), $this->approvalData())->assertOk()->json('data.invoice_id');
        $invoice = Invoice::findOrFail($invoiceId);
        foreach ([['amount' => 3], ['user_id' => $this->owner->id], ['source' => 'manual'], ['status' => 'cancelled'], ['status' => 'paid'], ['status' => 'pending']] as $change) {
            try {
                $invoice->fresh()->update($change);
                $this->fail('Controlled invoice changes must fail');
            } catch (HttpException $exception) {
                $this->assertSame(422, $exception->getStatusCode());
            }
        }
        try {
            $invoice->fresh()->delete();
            $this->fail('Controlled invoices must not be deleted');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->travelTo(now()->setDate(2026, 11, 1));
        app(ClubInvoicePaymentService::class)->record($invoice, ['amount' => 0.5, 'method' => 'bank'], $this->owner);
        $this->assertSame(150, $invoice->fresh()->outstandingCents());
        app(ClubInvoicePaymentService::class)->record($invoice, ['method' => 'bank'], $this->owner);
        $this->assertSame('paid', $invoice->fresh()->status);
        try {
            $invoice->fresh()->update(['status' => 'open']);
            $this->fail('Paid recharge cannot be reopened without correcting its receipts');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $url = "/api/v1/clubs/{$this->club->id}/membership/datev-export?from=2026-11-01&to=2026-11-30";
        $csv = $this->get($url)->assertOk()->streamedContent();
        $this->assertStringContainsString('0,50;S;EUR;1200;4990;', $csv);
        $this->assertStringContainsString('1,50;S;EUR;1200;4990;', $csv);
        $this->assertStringContainsString('Gebührenweiterbelastung', $csv);
        $this->club->update(['datev_bank_account' => '4990']);
        $this->getJson($url)->assertUnprocessable();
        $this->postJson($this->path('fee-corrections'), $this->correctionData(['amount_cents' => 0, 'booked_on' => '2026-11-01']))->assertOk();
        $this->assertDatabaseHas('club_sepa_fee_recharges', ['id' => $id, 'review_required' => true]);
        $this->assertSame('2.00', $invoice->fresh()->amount);
    }

    public function test_fee_recharge_approval_rolls_back_invoice_and_approval_when_audit_fails(): void
    {
        $id = $this->prepareRecharge();
        $count = Invoice::count();
        $event = 'eloquent.creating: '.Activity::class;
        Event::listen($event, function ($activity) {
            if ($activity->type === 'club.sepa.fee_recharge_approved') {
                throw new \RuntimeException('Approval audit failure');
            }
        });
        try {
            app(ClubSepaFeeRechargeService::class)->approve($this->item, ClubSepaFeeRecharge::findOrFail($id), $this->owner, '4990');
            $this->fail('Expected approval failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Approval audit failure', $exception->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertSame($count, Invoice::count());
        $this->assertDatabaseHas('club_sepa_fee_recharges', ['id' => $id, 'status' => 'draft', 'invoice_id' => null]);
    }

    private function rechargeData(array $extra = []): array
    {
        return array_replace(['confirmed' => true, 'request_id' => (string) Str::uuid(),
            'expected_revision' => 0, 'amount_cents' => 200, 'due_date' => '2026-10-20',
            'basis' => 'Recorded basis to be reviewed by the approver', 'reason' => 'Documented individual bank charge'], $extra);
    }

    public function test_fee_recharge_proposal_is_idempotent_and_does_not_create_member_debt(): void
    {
        $this->postJson($this->path('return'), $this->returned())->assertOk();
        $this->postJson($this->path('fee'), $this->feeData())->assertOk();
        $before = Invoice::count();
        $data = $this->rechargeData();
        $id = $this->postJson($this->path('fee-recharges'), $data)->assertOk()
            ->assertJsonPath('data.status', 'draft')->assertJsonPath('data.fee_amount_cents', 350)
            ->assertJsonPath('data.member_id', $this->invoice->user_id)->json('data.id');
        $this->postJson($this->path('fee-recharges'), $data)->assertOk()->assertJsonPath('data.id', $id);
        $this->postJson($this->path('fee-recharges'), array_replace($data, ['amount_cents' => 100]))->assertUnprocessable();
        $this->postJson($this->path('fee-recharges'), $this->rechargeData())->assertUnprocessable();
        $this->assertSame($before, Invoice::count());
        $this->assertSame(8000, $this->invoice->fresh()->outstandingCents());
        $this->assertDatabaseCount('club_sepa_fee_recharges', 1);
        $this->assertSame(1, Activity::where('type', 'club.sepa.fee_recharge_proposed')->count());
        $this->getJson($this->base())->assertOk()->assertJsonPath('fee_recharge_drafts_available', true)
            ->assertJsonPath('data.data.0.items.0.settlement.fee_recharges.0.status', 'draft');
    }

    public function test_fee_recharge_cancel_preserves_history_releases_reservation_and_retries_after_due_date(): void
    {
        $this->postJson($this->path('return'), $this->returned())->assertOk();
        $this->postJson($this->path('fee'), $this->feeData())->assertOk();
        $data = $this->rechargeData();
        $id = $this->postJson($this->path('fee-recharges'), $data)->assertOk()->json('data.id');
        $cancel = $this->path("fee-recharges/{$id}/cancel");
        $body = ['confirmed' => true, 'reason' => 'Proposal withdrawn after review'];
        $this->postJson($cancel, $body)->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.active_settlement_id', null);
        $this->postJson($cancel, $body)->assertOk();
        $this->postJson($cancel, ['confirmed' => true, 'reason' => 'Different'])->assertUnprocessable();
        $this->travelTo(now()->setDate(2026, 10, 21));
        $this->postJson($this->path('fee-recharges'), $data)->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->postJson($this->path('fee-recharges'), $this->rechargeData(['due_date' => '2026-11-01']))->assertOk()->assertJsonPath('data.status', 'draft');
        $this->assertDatabaseCount('club_sepa_fee_recharges', 2);
        $this->assertSame(1, Activity::where('type', 'club.sepa.fee_recharge_cancelled')->count());
    }

    public function test_fee_recharge_validates_current_fee_and_permissions_without_new_bookings(): void
    {
        $this->postJson($this->path('fee-recharges'), $this->rechargeData())->assertUnprocessable();
        $this->postJson($this->path('return'), $this->returned())->assertOk();
        $this->postJson($this->path('fee'), $this->feeData())->assertOk();
        foreach ([['amount_cents' => 351], ['amount_cents' => 0], ['expected_revision' => 1], ['due_date' => '2026-10-10'],
            ['basis' => ' '], ['reason' => ' '], ['confirmed' => false]] as $invalid) {
            $this->postJson($this->path('fee-recharges'), $this->rechargeData($invalid))->assertUnprocessable();
        }
        $this->postJson($this->path('fee-corrections'), $this->correctionData(['amount_cents' => 100]))->assertOk();
        $this->postJson($this->path('fee-recharges'), $this->rechargeData())->assertUnprocessable();
        $this->postJson($this->path('fee-recharges'), $this->rechargeData(['expected_revision' => 1]))->assertUnprocessable();
        Sanctum::actingAs($this->invoice->user);
        $this->postJson($this->path('fee-recharges'), $this->rechargeData(['expected_revision' => 1, 'amount_cents' => 100]))->assertForbidden();
        $this->assertDatabaseCount('club_sepa_fee_recharges', 0);
    }

    public function test_fee_recharge_snapshot_survives_later_fee_correction_and_foreign_cancel_is_rejected(): void
    {
        $this->postJson($this->path('return'), $this->returned())->assertOk();
        $this->postJson($this->path('fee'), $this->feeData())->assertOk();
        $id = $this->postJson($this->path('fee-recharges'), $this->rechargeData())->assertOk()->json('data.id');
        $this->postJson($this->path('fee-corrections'), $this->correctionData(['amount_cents' => 0]))->assertOk();
        $this->assertDatabaseHas('club_sepa_fee_recharges', ['id' => $id, 'fee_amount_cents' => 350, 'amount_cents' => 200, 'fee_revision' => 0]);
        $foreignClub = Club::factory()->create(['owner_id' => $this->reviewer->id]);
        $foreign = ClubSepaFeeRecharge::findOrFail($id)->replicate();
        $foreign->club_id = $foreignClub->id;
        $foreign->active_settlement_id = null;
        $foreign->request_id = (string) Str::uuid();
        $foreign->save();
        $this->postJson($this->path("fee-recharges/{$foreign->id}/cancel"), ['confirmed' => true, 'reason' => 'Wrong club'])->assertNotFound();
        $this->assertSame('draft', $foreign->fresh()->status);
    }

    public function test_fee_recharge_creation_and_reservation_roll_back_on_audit_failure(): void
    {
        $this->postJson($this->path('return'), $this->returned())->assertOk();
        $this->postJson($this->path('fee'), $this->feeData())->assertOk();
        $event = 'eloquent.creating: '.Activity::class;
        Event::listen($event, function ($activity) {
            if ($activity->type === 'club.sepa.fee_recharge_proposed') {
                throw new \RuntimeException('Simulated audit failure');
            }
        });
        try {
            app(ClubSepaFeeRechargeService::class)->propose($this->item, $this->reviewer, $this->rechargeData());
            $this->fail('Expected audit failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated audit failure', $exception->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertDatabaseCount('club_sepa_fee_recharges', 0);
        $this->postJson($this->path('fee-recharges'), $this->rechargeData())->assertOk();
    }

    private function feeData(array $overrides = []): array
    {
        return array_replace(['confirmed' => true, 'booked_on' => '2026-10-11', 'reference' => 'BANK-FEE-1', 'amount_cents' => '350'], $overrides);
    }

    public function test_return_fee_creates_one_bank_expense_without_changing_member_debt(): void
    {
        $this->postJson($this->path('return'), $this->returned())->assertOk();
        $this->assertDatabaseCount('club_finance_entries', 0);
        $this->postJson($this->path('fee'), $this->feeData())->assertOk()->assertJsonPath('data.fee_entry.amount', '3.50');
        $this->postJson($this->path('fee'), $this->feeData())->assertOk();
        $this->assertDatabaseCount('club_finance_entries', 1);
        $this->assertDatabaseHas('club_finance_entries', ['club_id' => $this->club->id, 'type' => 'expense', 'account' => 'bank', 'category' => 'sepa_return_fee', 'amount' => 3.5]);
        $this->assertSame(1, Activity::where('type', 'club.sepa.fee_recorded')->count());
        $this->assertSame('100.00', $this->invoice->fresh()->amount);
        $this->assertSame(2000, $this->invoice->fresh()->receivedCents());
        $this->assertSame(8000, $this->invoice->fresh()->outstandingCents());
        $this->getJson($this->base())->assertOk()->assertJsonPath('fees_available', true)
            ->assertJsonPath('data.data.0.items.0.settlement.fee_entry.amount', '3.50');
        Sanctum::actingAs($this->owner);
        $overview = $this->getJson("/api/v1/clubs/{$this->club->id}")->assertOk();
        $this->assertSame(3.5, (float) $overview->json('data.management.summary.expense_period_total'));
        $this->assertSame(16.5, (float) $overview->json('data.management.summary.total_balance'));
        $this->postJson($this->path('fee'), $this->feeData(['amount_cents' => 400]))->assertUnprocessable();
        $this->assertDatabaseCount('club_finance_entries', 1);
    }

    public function test_return_fee_links_an_existing_expense_without_copying_it(): void
    {
        $this->postJson($this->path('return'), $this->returned())->assertOk();
        $entry = ClubFinanceEntry::create(['club_id' => $this->club->id, 'user_id' => $this->owner->id,
            'type' => 'expense', 'account' => 'bank', 'title' => 'Bank expense', 'amount' => 3.5,
            'booked_on' => '2026-10-11', 'reference' => 'bank-fee-1']);
        $this->postJson($this->path('fee'), $this->feeData())->assertUnprocessable();
        $payload = $this->feeData(['finance_entry_id' => $entry->id]);
        $this->postJson($this->path('fee'), $payload)->assertOk()->assertJsonPath('data.fee_created_entry', false);
        $this->postJson($this->path('fee'), $payload)->assertOk();
        $this->assertDatabaseCount('club_finance_entries', 1);
        $this->assertSame('bank-fee-1', $entry->fresh()->reference);
        $this->assertSame(1, Activity::where('type', 'club.sepa.fee_recorded')->count());
    }

    public function test_return_fee_rejects_wrong_expenses_dates_missing_confirmation_and_rights(): void
    {
        $this->postJson($this->path('fee'), $this->feeData())->assertUnprocessable();
        $this->postJson($this->path('return'), $this->returned())->assertOk();
        foreach ([['confirmed' => false], ['amount_cents' => 0], ['amount_cents' => 1.5], ['booked_on' => '2026-10-10'], ['booked_on' => '2026-10-12']] as $invalid) {
            $this->postJson($this->path('fee'), $this->feeData($invalid))->assertUnprocessable();
        }
        $other = Club::factory()->create(['owner_id' => $this->owner->id]);
        $entry = ClubFinanceEntry::create(['club_id' => $other->id, 'type' => 'expense', 'account' => 'bank', 'title' => 'Other', 'amount' => 3.5, 'booked_on' => '2026-10-11', 'reference' => 'BANK-FEE-1']);
        foreach ([['club_id' => $other->id], ['club_id' => $this->club->id, 'account' => 'cash'], ['account' => 'bank', 'type' => 'income'], ['type' => 'expense', 'amount' => 9]] as $changes) {
            $entry->update($changes);
            $this->postJson($this->path('fee'), $this->feeData(['finance_entry_id' => $entry->id]))->assertUnprocessable();
        }
        Sanctum::actingAs($this->invoice->user);
        $this->postJson($this->path('fee'), $this->feeData())->assertForbidden();
        $this->assertNull($this->item->settlement()->first()->fee_finance_entry_id);
        $this->assertDatabaseCount('club_finance_entries', 1);
    }

    public function test_fee_expenses_cannot_be_overwritten_through_regular_finance_updates(): void
    {
        $this->postJson($this->path('return'), $this->returned())->assertOk();
        $this->postJson($this->path('fee'), $this->feeData())->assertOk();
        $entry = $this->item->settlement()->first()->feeEntry;
        $payload = ['type' => 'expense', 'account' => 'bank', 'title' => 'Changed fee', 'amount' => 10, 'booked_on' => '2026-10-11', 'reference' => 'OTHER'];
        $this->putJson("/api/v1/clubs/{$this->club->id}/finance-entries/{$entry->id}", $payload)->assertUnprocessable();
        $this->actingAs($this->owner)->putJson(route('auth.club-memberships.finance-entries.update', [$this->club, $entry]), $payload)->assertUnprocessable();
        $this->assertSame('3.50', $entry->fresh()->amount);
        $unlinked = $entry->replicate();
        $unlinked->reference = 'UNLINKED';
        $unlinked->save();
        $this->putJson("/api/v1/clubs/{$this->club->id}/finance-entries/{$unlinked->id}", $payload)->assertOk();
        $this->assertSame('10.00', $unlinked->fresh()->amount);
    }

    public function test_fee_reference_normalization_does_not_duplicate_unicode_bank_expenses(): void
    {
        $this->postJson($this->path('return'), $this->returned())->assertOk();
        $entry = ClubFinanceEntry::create(['club_id' => $this->club->id, 'type' => 'expense', 'account' => 'bank', 'title' => 'Fee', 'amount' => 3.5, 'booked_on' => '2026-10-11', 'reference' => ' gebühr-ß ']);
        $data = $this->feeData(['reference' => 'GEBÜHR-SS']);
        $this->postJson($this->path('fee'), $data)->assertUnprocessable();
        $this->postJson($this->path('fee'), $data + ['finance_entry_id' => $entry->id])->assertOk();
        $this->assertDatabaseCount('club_finance_entries', 1);
    }

    public function test_fee_schema_can_be_rolled_back_and_reapplied_without_changing_returns(): void
    {
        $this->postJson($this->path('return'), $this->returned())->assertOk();
        $migration = require database_path('migrations/2026_09_23_000004_link_sepa_return_fee_expenses.php');
        $migration->down();
        $this->getJson($this->base())->assertOk()->assertJsonPath('fees_available', false);
        $this->postJson($this->path('fee'), $this->feeData())->assertStatus(503);
        $this->assertSame('returned', $this->item->settlement()->first()->status);
        $this->assertDatabaseCount('club_finance_entries', 0);
        $migration->up();
        $this->getJson($this->base())->assertOk()->assertJsonPath('fees_available', true);
        $this->postJson($this->path('fee'), $this->feeData())->assertOk();
        $this->assertSame(8000, $this->invoice->fresh()->outstandingCents());
    }

    public function test_one_expense_cannot_pay_fees_for_two_return_positions(): void
    {
        $this->postJson($this->path('return'), $this->returned())->assertOk();
        $this->postJson($this->path('fee'), $this->feeData())->assertOk();
        $entryId = $this->item->settlement()->first()->fee_finance_entry_id;
        [, $item] = $this->secondReturnPosition();
        app(ClubSepaSettlementService::class)->returnDebit($item, $this->owner, ['booked_on' => '2026-10-11', 'reference' => 'BANK-RETURN-2', 'reason' => 'MS03']);
        $path = $this->base()."/{$this->batch->id}/items/{$item->id}/fee";
        $this->postJson($path, $this->feeData(['finance_entry_id' => $entryId]))->assertUnprocessable();
        $this->assertNull($item->settlement()->first()->fee_finance_entry_id);
        $this->assertDatabaseCount('club_finance_entries', 1);
    }

    private function mappedReturnCsv(): string
    {
        return "Buchungstag;Betrag;Währung;Referenz;Grund;EndToEnd;Gebühr\n11.10.2026;-80,00;EUR;BANK-MAPPED;MS03;R-1;3,50\n";
    }

    private function returnMapping(): array
    {
        return ['booking_date' => 0, 'amount' => 1, 'currency' => 2, 'reference' => 3, 'reason' => 4, 'end_to_end_id' => 5];
    }

    public function test_csv_column_inspection_is_read_only_and_mapping_requires_explicit_exclusions(): void
    {
        $csv = $this->mappedReturnCsv();
        $this->postJson($this->importPath('columns'), ['file' => $this->returnUpload($csv)])
            ->assertOk()->assertJsonPath('data.row_count', 1)->assertJsonCount(7, 'data.columns')
            ->assertJsonMissing(['MS03'])->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private');
        $this->assertDatabaseCount('club_sepa_settlements', 0);
        $this->previewReturns($csv)->assertUnprocessable();
        $payload = ['file' => $this->returnUpload($csv), 'mapping' => $this->returnMapping()];
        $this->postJson($this->importPath('preview'), $payload)->assertUnprocessable();
        $payload['ignored_columns'] = [6];
        $preview = $this->postJson($this->importPath('preview'), $payload)->assertOk()
            ->assertJsonPath('data.can_import', true)->assertJsonPath('data.rows.0.amount_cents', -8000)
            ->assertJsonPath('data.ignored_columns', ['gebühr']);
        $token = $preview->json('data.preview_token');
        $this->postJson($this->importPath('import'), [
            'file' => $this->returnUpload($csv), 'preview_token' => $token, 'confirmed' => true,
            'confirm_unlinked' => true, 'mapping' => ['amount' => 6],
        ])->assertUnprocessable();
        $this->assertDatabaseCount('club_sepa_settlements', 0);
        $this->importReturns($csv, $token, true)->assertOk()->assertJsonPath('data.imported', 1);
        $result = $this->item->settlement()->firstOrFail();
        $this->assertSame('BANK-MAPPED', $result->return_reference);
        $this->assertSame(0, $result->return_fee_cents);
        $this->assertSame(2000, $this->invoice->fresh()->receivedCents());
        $this->importReturns($csv, $token, true)->assertOk()->assertJsonPath('data.already_returned', 1);
    }

    public function test_csv_invalid_mappings_are_rejected_without_booking(): void
    {
        $mapping = $this->returnMapping();
        foreach ([
            [array_diff_key($mapping, ['amount' => true]), [1, 6]],
            [array_replace($mapping, ['amount' => 0]), [6]],
            [array_replace($mapping, ['amount' => 50]), [6]],
            [array_replace($mapping, ['amount' => -1]), [6]],
            [array_replace($mapping, ['amount' => 'oops']), [6]],
            [$mapping + ['unknown' => 6], []],
            [$mapping, [6, 6]],
            [$mapping, [1, 6]],
        ] as [$chosen, $ignored]) {
            $this->postJson($this->importPath('preview'), [
                'file' => $this->returnUpload($this->mappedReturnCsv()), 'mapping' => $chosen, 'ignored_columns' => $ignored,
            ])->assertUnprocessable();
        }
        $this->assertDatabaseCount('club_sepa_settlements', 0);
    }

    public function test_csv_column_inspection_rejects_ambiguous_headers_and_unauthorized_access(): void
    {
        foreach (["Amount;amount\n1;2\n", "A;;B\n1;2;3\n", "A;B\n1\n"] as $csv) {
            $this->postJson($this->importPath('columns'), ['file' => $this->returnUpload($csv)])->assertUnprocessable();
        }
        Sanctum::actingAs($this->invoice->user);
        $this->postJson($this->importPath('columns'), ['file' => $this->returnUpload($this->mappedReturnCsv())])->assertForbidden();
    }

    private function secondReturnPosition(): array
    {
        $invoice = $this->invoice->replicate();
        $invoice->number = 'R-2';
        $invoice->status = 'open';
        $invoice->paid_at = null;
        $invoice->save();
        app(ClubInvoicePaymentService::class)->record($invoice, ['amount' => 20, 'method' => 'cash'], $this->owner);
        $item = $this->item->replicate();
        $item->invoice_id = $invoice->id;
        $item->reserved_invoice_id = $invoice->id;
        $item->debtor_snapshot = array_replace($this->item->debtor_snapshot, ['number' => 'R-2']);
        $item->save();
        app(ClubSepaSettlementService::class)->settle($item, $this->owner, ['booked_on' => '2026-10-10', 'reference' => 'BANK-CREDIT-2']);

        return [$invoice, $item];
    }

    public function test_csv_rolls_back_earlier_rows_and_audit_if_a_later_booking_fails(): void
    {
        $this->postJson($this->path('settle'), $this->receipt())->assertOk();
        [$secondInvoice, $secondItem] = $this->secondReturnPosition();
        $csv = $this->returnCsv()."R-2;2026-10-11;-80;EUR;BANK-RETURN-2;MS03\n";
        $token = $this->previewReturns($csv)->assertOk()->assertJsonPath('data.can_import', true)->json('data.preview_token');
        $auditCount = Activity::count();
        $actual = app(ClubSepaSettlementService::class);
        $calls = 0;
        $this->mock(ClubSepaSettlementService::class, function ($mock) use ($actual, &$calls) {
            $mock->shouldReceive('returnDebit')->twice()->andReturnUsing(function ($item, $actor, $data) use ($actual, &$calls) {
                if (++$calls === 2) {
                    // Fail after the first real nested transaction has committed.
                    $this->assertSame('returned', $this->item->settlement()->first()->status);
                    abort(422, 'Simulated second-row booking failure');
                }

                return $actual->returnDebit($item, $actor, $data);
            });
        });
        $this->importReturns($csv, $token)->assertUnprocessable();
        $this->assertSame(2, $calls);
        foreach ([$this->item, $secondItem] as $item) {
            $result = $item->settlement()->firstOrFail();
            $this->assertSame('settled', $result->status);
            $this->assertSame('paid', $result->payment->status);
            $this->assertNull($result->return_reference);
        }
        foreach ([$this->invoice, $secondInvoice] as $invoice) {
            $this->assertSame('paid', $invoice->fresh()->status);
            $this->assertSame(10000, $invoice->fresh()->receivedCents());
        }
        $this->assertSame($auditCount, Activity::count());
        $this->assertDatabaseCount('payments', 4);
    }

    public function test_csv_two_returns_preserve_each_partial_payment_and_repeat_without_new_audits(): void
    {
        $this->postJson($this->path('settle'), $this->receipt())->assertOk();
        [$secondInvoice] = $this->secondReturnPosition();
        $csv = $this->returnCsv()."R-2;2026-10-11;-80;EUR;BANK-RETURN-2;MS03\n";
        $token = $this->previewReturns($csv)->assertOk()->json('data.preview_token');
        $this->importReturns($csv, $token)->assertOk()->assertJsonPath('data.imported', 2);
        foreach ([$this->invoice, $secondInvoice] as $invoice) {
            $this->assertSame(2000, $invoice->fresh()->receivedCents());
            $this->assertSame(8000, $invoice->fresh()->outstandingCents());
        }
        $audits = Activity::where('type', 'club.sepa.returned')->count();
        $this->assertSame(2, $audits);
        $this->importReturns($csv, $token)->assertOk()->assertJsonPath('data.imported', 0)->assertJsonPath('data.already_returned', 2);
        $this->assertSame($audits, Activity::where('type', 'club.sepa.returned')->count());
        $this->assertDatabaseCount('payments', 4);
    }

    public function test_manual_return_rejects_a_payment_or_invoice_moved_to_another_club(): void
    {
        $this->postJson($this->path('settle'), $this->receipt())->assertOk();
        $other = Club::factory()->create(['owner_id' => $this->owner->id]);
        $payment = $this->item->settlement()->firstOrFail()->payment;
        $payment->update(['club_id' => $other->id]);
        $this->postJson($this->path('return'), $this->returned())->assertUnprocessable();
        $this->assertSame('paid', $payment->fresh()->status);
        $payment->update(['club_id' => $this->club->id]);
        $this->invoice->update(['club_id' => $other->id]);
        $this->postJson($this->path('return'), $this->returned())->assertUnprocessable();
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('settled', $this->item->settlement()->first()->status);
    }

    public function test_one_bank_reference_cannot_book_a_second_position(): void
    {
        $this->postJson($this->path('settle'), $this->receipt())->assertOk();
        $other = $this->invoice->replicate();
        $other->number = 'R-2';
        $other->status = 'open';
        $other->save();
        $item = $this->item->replicate();
        $item->invoice_id = $other->id;
        $item->reserved_invoice_id = $other->id;
        $item->save();
        $this->postJson($this->base()."/{$this->batch->id}/items/{$item->id}/settle", $this->receipt())->assertUnprocessable();
        $this->assertSame(0, $other->receivedCents());
        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseCount('club_sepa_settlements', 1);
    }
}
