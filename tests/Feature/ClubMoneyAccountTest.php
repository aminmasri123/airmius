<?php

namespace Tests\Feature;

use App\Models\BankTransaction;
use App\Models\Club;
use App\Models\ClubFinanceEntry;
use App\Models\ClubMoneyAccount;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\ClubMoneyAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubMoneyAccountTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        Notification::fake();
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $plan = SubscriptionPlan::firstOrCreate(['slug' => 'pro'], ['target_actor' => 'verein', 'name' => 'Club',
            'monthly_price_cents' => 2990, 'yearly_price_cents' => 29900, 'currency' => 'EUR', 'features' => [], 'is_active' => true]);
        $club->currentSubscription()->updateOrCreate([], ['subscription_plan_id' => $plan->id, 'status' => 'active', 'billing_interval' => 'monthly']);
        Sanctum::actingAs($owner);

        return [$club, $owner];
    }

    public function test_multiple_accounts_keep_separate_balances_and_bank_details_can_be_updated_without_changing_opening(): void
    {
        [$club] = $this->context();
        $url = "/api/v1/clubs/{$club->id}";
        $bank = $this->postJson($url.'/money-accounts', ['name' => 'Hauptkonto', 'type' => 'bank', 'opening_cents' => 12000,
            'opened_on' => now()->toDateString(), 'iban' => 'de89 3704 0044 0532 0130 00', 'bic' => 'cobadeffxxx', 'bank_name' => 'Bank'])->assertCreated()->json('data.id');
        $cash = $this->postJson($url.'/money-accounts', ['name' => 'Barkasse', 'type' => 'cash', 'opening_cents' => 500,
            'opened_on' => now()->toDateString()])->assertCreated()->json('data.id');
        $this->putJson($url.'/money-accounts/'.$bank, ['name' => 'Vereinskonto', 'is_active' => true,
            'iban' => 'DE89370400440532013000', 'opening_cents' => 999999, 'type' => 'cash'])->assertOk()
            ->assertJsonPath('data.iban', 'DE89370400440532013000')->assertJsonPath('data.type', 'bank');
        $accounts = collect($this->getJson($url.'/finance-workspace')->assertOk()->json('data.accounts'))->keyBy('id');
        $this->assertSame(12000, $accounts[$bank]['balance_cents']);
        $this->assertSame(500, $accounts[$cash]['balance_cents']);
        $this->assertSame(2, ClubFinanceEntry::count());
        $this->putJson($url.'/money-accounts/'.$bank, ['name' => 'Bank', 'is_active' => true, 'iban' => 'DE00370400440532013000'])->assertUnprocessable();
    }

    public function test_archive_preserves_balance_and_rejects_new_transfers_and_bookings(): void
    {
        [$club] = $this->context();
        $url = "/api/v1/clubs/{$club->id}";
        $bank = $this->postJson($url.'/money-accounts', ['name' => 'Bank', 'type' => 'bank', 'opening_cents' => 5000, 'opened_on' => now()->toDateString()])->json('data.id');
        $cash = ClubMoneyAccount::create(['club_id' => $club->id, 'type' => 'cash', 'name' => 'Cash']);
        $this->putJson($url.'/money-accounts/'.$bank, ['name' => 'Bank', 'is_active' => false])->assertOk();
        $accounts = collect($this->getJson($url.'/finance-workspace')->assertOk()->json('data.accounts'))->keyBy('id');
        $this->assertFalse($accounts[$bank]['is_active']);
        $this->assertSame(5000, $accounts[$bank]['balance_cents']);
        $this->postJson($url.'/money-transfers', ['from_id' => $bank, 'to_id' => $cash->id, 'amount_cents' => 100,
            'booked_on' => now()->toDateString(), 'idempotency_key' => 'a58083aa-65d2-42da-bc09-4525bdd8cf62'])->assertUnprocessable();
        $this->postJson($url.'/finance-entries', ['type' => 'expense', 'account' => 'bank', 'title' => 'Balls', 'amount' => 5, 'club_money_account_id' => $bank])->assertUnprocessable();
        $this->assertSame(1, ClubFinanceEntry::count());
    }

    public function test_invoice_payment_uses_the_selected_bank_only(): void
    {
        [$club, $owner] = $this->context();
        $first = ClubMoneyAccount::create(['club_id' => $club->id, 'name' => 'First', 'type' => 'bank']);
        $second = ClubMoneyAccount::create(['club_id' => $club->id, 'name' => 'Second', 'type' => 'bank']);
        $invoice = Invoice::create(['club_id' => $club->id, 'user_id' => $owner->id, 'number' => 'BANK-1', 'title' => 'Contribution', 'amount' => 50, 'status' => 'open', 'due_date' => now()->addWeek()]);
        $this->postJson("/api/v1/clubs/{$club->id}/membership-invoices/{$invoice->id}/payments", ['method' => 'bank_transfer', 'club_money_account_id' => $second->id])->assertOk();
        $this->assertEquals($second->id, Payment::first()->club_money_account_id);
        $accounts = collect($this->getJson("/api/v1/clubs/{$club->id}/finance-workspace")->json('data.accounts'))->keyBy('id');
        $this->assertSame(0, $accounts[$first->id]['balance_cents']);
        $this->assertSame(5000, $accounts[$second->id]['balance_cents']);
    }

    public function test_foreign_account_updates_and_payments_are_rejected(): void
    {
        [$club, $owner] = $this->context();
        $foreign = ClubMoneyAccount::create(['club_id' => Club::factory()->create(['owner_id' => User::factory()])->id, 'name' => 'Foreign', 'type' => 'bank']);
        $this->putJson("/api/v1/clubs/{$club->id}/money-accounts/{$foreign->id}", ['name' => 'Changed', 'is_active' => false])->assertNotFound();
        $invoice = Invoice::create(['club_id' => $club->id, 'user_id' => $owner->id, 'number' => 'BANK-2', 'title' => 'Contribution', 'amount' => 50, 'status' => 'open', 'due_date' => now()->addWeek()]);
        $this->postJson("/api/v1/clubs/{$club->id}/membership-invoices/{$invoice->id}/payments", ['method' => 'bank_transfer', 'club_money_account_id' => $foreign->id])->assertUnprocessable();
        $this->assertSame(0, Payment::count());
    }

    public function test_bank_preview_does_not_write_and_import_keeps_the_selected_account_until_confirmation(): void
    {
        [$club, $owner] = $this->context();
        $first = ClubMoneyAccount::create(['club_id' => $club->id, 'name' => 'First', 'type' => 'bank']);
        $second = ClubMoneyAccount::create(['club_id' => $club->id, 'name' => 'Second', 'type' => 'bank']);
        $invoice = Invoice::create(['club_id' => $club->id, 'user_id' => $owner->id, 'number' => 'BANK-CSV', 'title' => 'Contribution', 'amount' => 50, 'status' => 'open', 'due_date' => now()->addWeek()]);
        $csv = "Datum;Betrag;Auftraggeber;Verwendungszweck\n".now()->toDateString().";50,00;Member;BANK-CSV\n";
        $payload = ['file' => UploadedFile::fake()->createWithContent('bank.csv', $csv), 'club_money_account_id' => $second->id];
        $url = "/api/v1/clubs/{$club->id}/bank-transactions";
        $this->postJson($url.'/preview', ['file' => UploadedFile::fake()->createWithContent('bank.csv', $csv)])->assertUnprocessable();
        $this->postJson($url.'/preview', $payload)->assertOk();
        $this->assertSame(0, BankTransaction::count());
        $this->assertSame(0, Payment::count());
        $this->postJson($url.'/import', $payload)->assertCreated();
        $transaction = BankTransaction::firstOrFail();
        $this->assertEquals($second->id, $transaction->club_money_account_id);
        $this->assertSame(0, Payment::count());
        $this->postJson($url."/{$transaction->id}/confirm")->assertOk();
        $this->assertEquals($second->id, Payment::firstOrFail()->club_money_account_id);
        $this->postJson($url.'/import', $payload)->assertCreated();
        $this->assertSame(1, BankTransaction::count());
        $this->assertSame(1, Payment::count());
        $this->assertNotSame(app(ClubMoneyAccountService::class)->transactionHash('same', $first->id), app(ClubMoneyAccountService::class)->transactionHash('same', $second->id));
        $this->postJson($url.'/import', ['file' => UploadedFile::fake()->createWithContent('bank.csv', $csv), 'club_money_account_id' => $first->id])->assertCreated();
        $this->assertSame(2, BankTransaction::count());
        $this->assertSame(1, Payment::count());
    }

    public function test_payment_method_correction_does_not_leave_a_cash_receipt_in_a_bank_account(): void
    {
        [$club, $owner] = $this->context();
        $bank = ClubMoneyAccount::create(['club_id' => $club->id, 'name' => 'Bank', 'type' => 'bank']);
        $cash = ClubMoneyAccount::create(['club_id' => $club->id, 'name' => 'Cash', 'type' => 'cash']);
        $payment = Payment::create(['club_id' => $club->id, 'user_id' => $owner->id, 'amount' => 10, 'method' => 'cash', 'status' => 'paid', 'club_money_account_id' => $cash->id, 'paid_at' => now()]);
        $this->putJson("/api/v1/clubs/{$club->id}/payments/{$payment->id}", ['amount' => 10, 'method' => 'bank_transfer', 'club_money_account_id' => $bank->id])->assertOk();
        $this->assertEquals($bank->id, $payment->fresh()->club_money_account_id);
        $accounts = collect($this->getJson("/api/v1/clubs/{$club->id}/finance-workspace")->json('data.accounts'))->keyBy('id');
        $this->assertSame(0, $accounts[$cash->id]['balance_cents']);
        $this->assertSame(1000, $accounts[$bank->id]['balance_cents']);
    }

    public function test_historical_unassigned_balances_remain_visible_and_account_mutations_require_finance_access(): void
    {
        [$club, $owner] = $this->context();
        $bank = ClubMoneyAccount::create(['club_id' => $club->id, 'name' => 'Bank', 'type' => 'bank']);
        Payment::create(['club_id' => $club->id, 'amount' => 12, 'method' => 'bank_transfer', 'status' => 'paid', 'paid_at' => now()]);
        $response = $this->getJson("/api/v1/clubs/{$club->id}/finance-workspace")->assertOk();
        $this->assertSame(1200, collect($response->json('data.unassigned_accounts'))->firstWhere('type', 'bank')['balance_cents']);
        $this->assertSame(0, collect($response->json('data.accounts'))->firstWhere('id', $bank->id)['balance_cents']);
        Sanctum::actingAs(User::factory()->create());
        $this->putJson("/api/v1/clubs/{$club->id}/money-accounts/{$bank->id}", ['name' => 'Changed', 'is_active' => false])->assertForbidden();
        $this->assertSame('Bank', $bank->fresh()->name);
    }

    public function test_sepa_account_resolution_uses_the_actual_iban_and_legacy_bank_duplicates_remain_protected(): void
    {
        [$club] = $this->context();
        $bank = ClubMoneyAccount::create(['club_id' => $club->id, 'name' => 'Bank', 'type' => 'bank', 'iban' => 'DE89370400440532013000', 'is_active' => true]);
        $service = app(ClubMoneyAccountService::class);
        $this->assertSame($bank->id, $service->forIban($club->id, 'de89 3704 0044 0532 0130 00'));
        $this->assertNull($service->forIban($club->id, 'DE02120300000000202051'));
        BankTransaction::create(['club_id' => $club->id, 'transaction_hash' => 'legacy-hash', 'amount' => 5, 'booking_date' => now(), 'status' => 'unmatched']);
        $this->assertTrue($service->isDuplicateTransaction($club->id, 'legacy-hash', $bank->id));
        $bank->update(['is_active' => false]);
        $this->assertNull($service->forIban($club->id, 'DE89370400440532013000'));
    }
}
