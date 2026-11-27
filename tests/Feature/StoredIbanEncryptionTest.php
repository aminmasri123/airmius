<?php

namespace Tests\Feature;

use App\Models\BankTransaction;
use App\Models\Club;
use App\Models\ClubBusinessPartner;
use App\Models\ClubExternalMember;
use App\Models\ClubMoneyAccount;
use App\Models\ClubMasterDataChangeRequest;
use App\Models\ClubMembershipRequest;
use App\Models\Invoice;
use App\Models\PayoutProfile;
use App\Models\User;
use App\Services\ClubMoneyAccountService;
use App\Services\ClubMembershipBankReconciliationService;
use App\Services\ClubContributionInvoiceRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StoredIbanEncryptionTest extends TestCase
{
    use RefreshDatabase;

    private const IBAN = 'DE89370400440532013000';

    public function test_iban_values_and_imported_bank_rows_are_encrypted_at_rest(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id, 'sepa_iban' => self::IBAN]);
        $club->users()->attach($member->id, [
            'role' => 'member', 'sepa_iban' => self::IBAN,
            'sepa_mandate_reference' => 'TEST-1', 'sepa_mandate_active' => true,
        ]);
        $external = ClubExternalMember::create([
            'club_id' => $club->id, 'email' => 'external@example.test', 'sepa_iban' => self::IBAN,
        ]);
        $account = ClubMoneyAccount::create([
            'club_id' => $club->id, 'name' => 'Test bank', 'type' => 'bank', 'iban' => self::IBAN,
        ]);
        $partner = ClubBusinessPartner::create([
            'club_id' => $club->id, 'name' => 'Test partner', 'iban' => self::IBAN,
        ]);
        $transaction = BankTransaction::create([
            'club_id' => $club->id, 'transaction_hash' => str_repeat('a', 64),
            'amount' => 5, 'currency' => 'EUR', 'debtor_iban' => self::IBAN,
            'raw_data' => ['Partner Iban' => self::IBAN],
        ]);
        $profile = PayoutProfile::create(['user_id' => $member->id, 'iban' => self::IBAN]);
        $application = ClubMembershipRequest::create([
            'club_id' => $club->id, 'user_id' => $member->id,
            'application_data' => ['sepa_iban' => self::IBAN],
        ]);
        $change = ClubMasterDataChangeRequest::create([
            'club_id' => $club->id, 'requested_by' => $owner->id,
            'fields' => ['sepa_iban'],
            'base_values' => ['sepa_iban' => self::IBAN],
            'proposed_values' => ['sepa_iban' => self::IBAN],
            'conflicts' => ['sepa_iban' => self::IBAN],
        ]);

        foreach ([
            ['clubs', 'sepa_iban', $club->id],
            ['club_external_members', 'sepa_iban', $external->id],
            ['club_money_accounts', 'iban', $account->id],
            ['club_business_partners', 'iban', $partner->id],
            ['bank_transactions', 'debtor_iban', $transaction->id],
            ['payout_profiles', 'iban', $profile->id],
        ] as [$table, $column, $id]) {
            $stored = DB::table($table)->where('id', $id)->value($column);
            $this->assertStringStartsWith('iban:v1:', $stored, "$table.$column");
            $this->assertStringNotContainsString(self::IBAN, $stored);
        }

        $storedMemberIban = DB::table('club_user')->where('club_id', $club->id)
            ->where('user_id', $member->id)->value('sepa_iban');
        $this->assertStringStartsWith('iban:v1:', $storedMemberIban);
        $this->assertStringNotContainsString(self::IBAN, $storedMemberIban);

        $storedRow = DB::table('bank_transactions')->where('id', $transaction->id)->value('raw_data');
        $this->assertStringStartsWith('iban:v1:', $storedRow);
        $this->assertStringNotContainsString(self::IBAN, $storedRow);
        foreach ([
            ['club_membership_requests', $application->id, 'application_data'],
            ['club_master_data_change_requests', $change->id, 'base_values'],
            ['club_master_data_change_requests', $change->id, 'proposed_values'],
            ['club_master_data_change_requests', $change->id, 'conflicts'],
        ] as [$table, $id, $column]) {
            $stored = DB::table($table)->where('id', $id)->value($column);
            $this->assertStringStartsWith('iban:v1:', $stored);
            $this->assertStringNotContainsString(self::IBAN, $stored);
        }
        $this->assertSame(self::IBAN, $club->fresh()->sepa_iban);
        $this->assertSame(self::IBAN, $club->users()->whereKey($member->id)->first()->pivot->sepa_iban);
        $this->assertSame(self::IBAN, $external->fresh()->sepa_iban);
        $this->assertSame(self::IBAN, $account->fresh()->iban);
        $this->assertSame(self::IBAN, $partner->fresh()->iban);
        $this->assertSame(self::IBAN, $transaction->fresh()->debtor_iban);
        $this->assertSame(['Partner Iban' => self::IBAN], $transaction->fresh()->raw_data);
        $this->assertSame(self::IBAN, $profile->fresh()->iban);
        $this->assertSame(self::IBAN, $application->fresh()->application_data['sepa_iban']);
        $this->assertSame(self::IBAN, $change->fresh()->proposed_values['sepa_iban']);
        $this->assertSame($account->id, app(ClubMoneyAccountService::class)->forIban($club->id, self::IBAN));

        $invoice = Invoice::create([
            'club_id' => $club->id, 'user_id' => $member->id, 'number' => 'ENCRYPT-1',
            'title' => 'Test contribution', 'amount' => 5, 'status' => 'open',
            'due_date' => now()->addWeek(),
        ]);
        $match = app(ClubMembershipBankReconciliationService::class)->matchInvoice($club, [
            'amount' => 5, 'purpose' => 'Contribution', 'debtor_iban' => self::IBAN,
            'debtor_name' => 'Unrelated account holder',
        ]);
        $this->assertSame($invoice->id, $match['invoice']?->id);
        $this->assertSame(95, $match['confidence']);

        $club->users()->updateExistingPivot($member->id, [
            'membership_status' => 'active', 'contribution_amount' => 5,
            'contribution_interval' => 'yearly',
            'contribution_next_invoice_on' => '2026-01-01',
            'payment_method' => 'sepa_debit',
            'sepa_mandate_signed_on' => '2025-12-01',
        ]);
        $run = app(ClubContributionInvoiceRunService::class)->preview($club, '2026-11-27');
        $this->assertSame(1, $run['direct_debit_count']);
    }

    public function test_migration_encrypts_existing_plaintext_and_is_reversible(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, ['role' => 'member']);
        DB::table('clubs')->where('id', $club->id)->update(['sepa_iban' => self::IBAN]);
        DB::table('club_user')->where('club_id', $club->id)->where('user_id', $member->id)
            ->update(['sepa_iban' => self::IBAN]);
        $this->assertSame(self::IBAN, DB::table('club_user')->where('club_id', $club->id)
            ->where('user_id', $member->id)->value('sepa_iban'));
        $transaction = BankTransaction::create([
            'club_id' => $club->id, 'transaction_hash' => str_repeat('b', 64),
            'amount' => 5, 'currency' => 'EUR',
        ]);
        DB::table('bank_transactions')->where('id', $transaction->id)->update([
            'debtor_iban' => self::IBAN,
            'raw_data' => json_encode(['iban' => self::IBAN], JSON_THROW_ON_ERROR),
        ]);

        $migration = require database_path('migrations/2026_11_27_000001_encrypt_stored_ibans.php');
        $migration->up();

        $stored = DB::table('clubs')->where('id', $club->id)->value('sepa_iban');
        $this->assertStringStartsWith('iban:v1:', $stored);
        $this->assertSame(self::IBAN, $club->fresh()->sepa_iban);
        $this->assertDatabaseHas('club_user', ['club_id' => $club->id, 'user_id' => $member->id]);
        $this->assertStringStartsWith('iban:v1:', DB::table('club_user')
            ->where('club_id', $club->id)->where('user_id', $member->id)->value('sepa_iban'));
        $this->assertStringStartsWith('iban:v1:', DB::table('bank_transactions')
            ->where('id', $transaction->id)->value('raw_data'));
        $this->assertSame(['iban' => self::IBAN], $transaction->fresh()->raw_data);

        $migration->down();
        $this->assertSame(self::IBAN, DB::table('clubs')->where('id', $club->id)->value('sepa_iban'));
        $this->assertSame(self::IBAN, DB::table('club_user')->where('club_id', $club->id)
            ->where('user_id', $member->id)->value('sepa_iban'));
        $this->assertSame(json_encode(['iban' => self::IBAN], JSON_THROW_ON_ERROR),
            DB::table('bank_transactions')->where('id', $transaction->id)->value('raw_data'));
    }
}
