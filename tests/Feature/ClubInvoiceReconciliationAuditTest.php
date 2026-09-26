<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Invoice;
use App\Models\User;
use App\Services\ClubInvoiceReconciliationAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClubInvoiceReconciliationAuditTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->member = User::factory()->create(['name' => 'Private Member Name', 'email' => 'private@example.test']);
        $this->club = Club::factory()->create(['owner_id' => $this->member->id]);
    }

    private function invoice(string $status = 'paid', string $amount = '100.00', ?Club $club = null): Invoice
    {
        return Invoice::create([
            'club_id' => ($club ?? $this->club)->id, 'user_id' => $this->member->id,
            'number' => 'PRIVATE-'.Invoice::count(), 'title' => 'Private invoice title',
            'status' => $status, 'amount' => $amount, 'due_date' => now()->addDays(7), 'paid_at' => $status === 'paid' ? now() : null,
        ]);
    }

    private function pay(Invoice $invoice, string $amount, string $status = 'paid', ?Club $club = null): void
    {
        $invoice->payments()->create([
            'club_id' => ($club ?? $this->club)->id, 'user_id' => $this->member->id,
            'amount' => $amount, 'status' => $status, 'paid_at' => now(),
            'reference' => 'PRIVATE-BANK-REFERENCE', 'notes' => 'Private payment note',
        ]);
    }

    public function test_distinguishes_legacy_missing_receipts_partial_paid_and_failed_receipts(): void
    {
        $legacy = $this->invoice();
        $partial = $this->invoice();
        $this->pay($partial, '20.01');
        $failed = $this->invoice();
        $this->pay($failed, '100.00', 'failed');
        $returned = $this->invoice();
        $this->pay($returned, '100.00', 'returned');
        $report = app(ClubInvoiceReconciliationAudit::class)->report($this->club, true);
        $this->assertSame(4, $report['invoices_flagged']);
        $this->assertSame(1, $report['reason_counts']['paid_without_payment_records']);
        $this->assertSame(1, $report['reason_counts']['paid_below_invoice_amount']);
        $this->assertSame(2, $report['reason_counts']['paid_without_settled_receipts']);
        $this->assertSame(7999, $report['findings'][1]['uncovered_amount_cents']);
        $this->assertSame(0, $legacy->fresh()->outstandingCents());
        $this->assertSame('paid', $partial->fresh()->status);
        $this->assertSame(0, $partial->fresh()->outstandingCents());
    }

    public function test_normal_open_partial_full_paid_and_zero_invoices_are_not_flagged(): void
    {
        $this->invoice('open');
        $partial = $this->invoice('overdue');
        $this->pay($partial, '33.33');
        $settled = $this->invoice();
        $this->pay($settled, '33.33');
        $this->pay($settled, '66.67');
        $this->invoice('paid', '0.00');
        $this->invoice('cancelled');
        $report = app(ClubInvoiceReconciliationAudit::class)->report($this->club, true);
        $this->assertSame(5, $report['invoices_scanned']);
        $this->assertSame(0, $report['invoices_flagged']);
        $this->assertFalse($report['requires_human_review']);
    }

    public function test_finds_covered_open_overpayment_cancellation_and_invalid_associations(): void
    {
        $covered = $this->invoice('open');
        $this->pay($covered, '100.00');
        $overpaid = $this->invoice();
        $this->pay($overpaid, '120.00');
        $cancelled = $this->invoice('cancelled');
        $this->pay($cancelled, '20.00');
        $other = Club::factory()->create(['owner_id' => $this->member->id]);
        $mismatch = $this->invoice();
        $this->pay($mismatch, '100.00', club: $other);
        $negative = $this->invoice();
        $this->pay($negative, '-5.00');
        $this->invoice('paid', '-1.00');
        $report = app(ClubInvoiceReconciliationAudit::class)->report($this->club, true);
        $this->assertSame(6, $report['invoices_flagged']);
        foreach (['open_with_covered_amount', 'overpaid', 'cancelled_with_receipts', 'payment_club_mismatch', 'negative_settled_payment', 'invalid_invoice_amount'] as $reason) {
            $this->assertSame(1, $report['reason_counts'][$reason], $reason);
        }
        $this->assertSame(2000, $report['findings'][1]['overpaid_amount_cents']);
    }

    public function test_report_is_read_only_club_scoped_and_excludes_personal_fields(): void
    {
        $invoice = $this->invoice();
        $this->pay($invoice, '20.00');
        $other = Club::factory()->create(['owner_id' => $this->member->id]);
        $this->invoice(club: $other);
        $before = [DB::table('invoices')->get()->toJson(), DB::table('payments')->get()->toJson()];
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });
        $report = app(ClubInvoiceReconciliationAudit::class)->report($this->club, true);
        foreach ($queries as $query) {
            $this->assertMatchesRegularExpression('/^select\b/i', $query);
        }
        $this->assertSame($before, [DB::table('invoices')->get()->toJson(), DB::table('payments')->get()->toJson()]);
        $this->assertSame(1, $report['invoices_scanned']);
        $this->assertSame($invoice->id, $report['findings'][0]['invoice_id']);
        foreach (['Private', 'private@example.test', 'PRIVATE-', 'user_id', 'iban'] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, json_encode($report));
        }
        $summary = app(ClubInvoiceReconciliationAudit::class)->report($this->club);
        $this->assertSame([], $summary['findings']);
        $this->assertSame(1, $summary['details_omitted']);
    }

    public function test_detail_limit_does_not_limit_scan_or_counts_across_chunks(): void
    {
        $rows = [];
        for ($i = 0; $i < 253; $i++) {
            $rows[] = ['club_id' => $this->club->id, 'user_id' => $this->member->id, 'number' => 'BULK-'.$i, 'title' => 'Test', 'amount' => '10.00', 'status' => 'paid', 'due_date' => now()->toDateString()];
        }
        DB::table('invoices')->insert($rows);
        $report = app(ClubInvoiceReconciliationAudit::class)->report($this->club, true, 2);
        $this->assertSame(253, $report['invoices_scanned']);
        $this->assertSame(253, $report['invoices_flagged']);
        $this->assertSame(253, $report['reason_counts']['paid_without_payment_records']);
        $this->assertCount(2, $report['findings']);
        $this->assertSame(251, $report['details_omitted']);
        $this->assertLessThan($report['findings'][1]['invoice_id'], $report['findings'][0]['invoice_id']);
        $next = app(ClubInvoiceReconciliationAudit::class)->report($this->club, true, 2, $report['next_after_id']);
        $this->assertSame(253, $next['invoices_flagged']);
        $this->assertSame(249, $next['details_remaining']);
        $this->assertGreaterThan($report['findings'][1]['invoice_id'], $next['findings'][0]['invoice_id']);
        $last = app(ClubInvoiceReconciliationAudit::class)->report($this->club, true, 2, (int) Invoice::max('id') - 1);
        $this->assertCount(1, $last['findings']);
        $this->assertNull($last['next_after_id']);
        $this->assertSame(0, $last['details_remaining']);
    }

    public function test_command_json_strict_and_detail_options(): void
    {
        $this->invoice();
        $command = 'airmius:audit-club-invoice-payments';
        $this->assertSame(0, Artisan::call($command, ['club' => $this->club->id, '--json' => true]));
        $summary = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('club-invoice-reconciliation.v1', $summary['contract']);
        $this->assertTrue($summary['read_only']);
        $this->assertSame([], $summary['findings']);
        $this->assertSame(1, Artisan::call($command, ['club' => $this->club->id, '--json' => true, '--strict' => true, '--details' => true, '--limit' => 1]));
        $this->assertCount(1, json_decode(Artisan::output(), true)['findings']);
        $this->assertSame(0, Artisan::call($command, ['club' => $this->club->id, '--details' => true]));
        $this->assertStringContainsString('Rechnungs-ID', Artisan::output());
        $this->assertSame(1, Artisan::call($command, ['club' => $this->club->id, '--json' => true, '--strict' => true, '--details' => true, '--after-id' => Invoice::max('id')]));
        $this->assertSame([], json_decode(Artisan::output(), true)['findings']);
    }

    public function test_command_rejects_invalid_scope_or_limits_and_empty_club_passes_strict(): void
    {
        $command = 'airmius:audit-club-invoice-payments';
        foreach (['-1', 'abc', '0', '99999999'] as $id) {
            $this->assertSame(2, Artisan::call($command, ['club' => $id, '--json' => true]));
        }
        foreach (['0', '201', 'foo', '-1'] as $limit) {
            $this->assertSame(2, Artisan::call($command, ['club' => $this->club->id, '--limit' => $limit]));
        }
        $this->assertSame(2, Artisan::call($command, ['club' => $this->club->id, '--after-id' => '-1']));
        $this->assertSame(0, Artisan::call($command, ['club' => $this->club->id, '--strict' => true, '--json' => true]));
        $this->assertSame(0, json_decode(Artisan::output(), true)['invoices_scanned']);
    }
}
