<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubBudget;
use App\Models\ClubDepartment;
use App\Models\ClubFinanceEntry;
use App\Models\ClubMoneyAccount;
use App\Models\ClubYearPeriod;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Team;
use App\Models\TeamFee;
use App\Models\User;
use App\Services\ClubFinanceBalanceService;
use App\Services\ClubFinanceScopeService;
use App\Services\ClubYearPeriodReportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubFinanceWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_and_department_budgets_count_only_their_payments_and_expenses(): void
    {
        [$club, $owner, $period] = $this->context();
        $department = ClubDepartment::create(['club_id' => $club->id, 'name' => 'Youth']);
        $a = Team::factory()->create(['club_id' => $club->id, 'club_department_id' => $department->id]);
        $b = Team::factory()->create(['club_id' => $club->id]);
        $root = $this->budget($club, $period);
        $dep = $this->budget($club, $period, ['scope_type' => 'department', 'club_department_id' => $department->id, 'parent_id' => $root->id]);
        $ba = $this->budget($club, $period, ['scope_type' => 'team', 'team_id' => $a->id, 'parent_id' => $dep->id]);
        $bb = $this->budget($club, $period, ['scope_type' => 'team', 'team_id' => $b->id, 'parent_id' => $root->id]);
        $invoice = Invoice::create(['club_id' => $club->id, 'user_id' => $owner->id, 'team_id' => $a->id,
            'club_department_id' => $department->id, 'club_budget_id' => $ba->id,
            'number' => 'TEAM-A', 'title' => 'Contribution', 'amount' => 100, 'status' => 'open', 'issued_at' => '2026-02-01', 'due_date' => '2026-02-15']);
        Payment::create(['club_id' => $club->id, 'invoice_id' => $invoice->id, 'amount' => 40, 'status' => 'paid', 'method' => 'bank_import', 'paid_at' => '2026-02-02']);
        $this->entry($club, $owner, ['team_id' => $a->id, 'club_budget_id' => $ba->id, 'amount' => 15]);
        $this->entry($club, $owner, ['team_id' => $b->id, 'club_budget_id' => $bb->id, 'amount' => 25]);
        $foreign = Club::factory()->create(['owner_id' => User::factory()]);
        $this->entry($foreign, $owner, ['amount' => 999]);
        Sanctum::actingAs($owner);
        $reports = collect($this->getJson("/api/v1/clubs/{$club->id}/budgets")->assertOk()->json('data.budgets'))->keyBy('id');
        $this->assertSame(4000, $reports[$ba->id]['financial_report']['actual_income_cents']);
        $this->assertSame(1500, $reports[$ba->id]['financial_report']['actual_expense_cents']);
        $this->assertSame(6000, $reports[$ba->id]['financial_report']['open_receivables_cents']);
        $this->assertSame(0, $reports[$bb->id]['financial_report']['actual_income_cents']);
        $this->assertSame(2500, $reports[$bb->id]['financial_report']['actual_expense_cents']);
        $this->assertSame(1500, $reports[$dep->id]['financial_report']['actual_expense_cents']);
        $this->assertSame(4000, $reports[$root->id]['financial_report']['actual_expense_cents']);
    }

    public function test_openings_and_repeated_transfers_change_balances_without_creating_turnover(): void
    {
        [$club, $owner, $period] = $this->context();
        Sanctum::actingAs($owner);
        $from = $this->postJson("/api/v1/clubs/{$club->id}/money-accounts", ['name' => 'Bank', 'type' => 'bank', 'opening_cents' => 10000, 'opened_on' => '2026-01-01'])->assertCreated()->json('data.id');
        $to = $this->postJson("/api/v1/clubs/{$club->id}/money-accounts", ['name' => 'Cash', 'type' => 'cash', 'opening_cents' => 0, 'opened_on' => '2026-01-01'])->assertCreated()->json('data.id');
        $payload = ['from_id' => $from, 'to_id' => $to, 'amount_cents' => 3000, 'booked_on' => '2026-02-01', 'idempotency_key' => 'a58083aa-65d2-42da-bc09-4525bdd8cf62'];
        $this->postJson("/api/v1/clubs/{$club->id}/money-transfers", $payload)->assertOk();
        $this->postJson("/api/v1/clubs/{$club->id}/money-transfers", $payload)->assertOk();
        $accounts = collect($this->getJson("/api/v1/clubs/{$club->id}/finance-workspace")->assertOk()->json('data.accounts'))->keyBy('id');
        $this->assertSame(7000, $accounts[$from]['balance_cents']);
        $this->assertSame(3000, $accounts[$to]['balance_cents']);
        $summary = app(ClubFinanceBalanceService::class)->summary($club);
        $this->assertEquals(100, $summary['total_balance']);
        $this->assertEquals(0, $summary['income_total']);
        $this->assertEquals(0, $summary['expense_total']);
        $yearReport = app(ClubYearPeriodReportService::class)->report($club, 'business', $period);
        $this->assertEquals(0, $yearReport['summary']['finance_entries']['income_amount']);
        $this->assertEquals(0, $yearReport['summary']['finance_entries']['expense_amount']);
        $this->assertSame(2, ClubFinanceEntry::where('entry_kind', 'transfer')->count());
        $this->postJson("/api/v1/clubs/{$club->id}/money-transfers", array_replace($payload, ['amount_cents' => 4000]))->assertUnprocessable();
    }

    public function test_bank_import_is_bank_income_and_period_uses_the_club_business_year(): void
    {
        [$club] = $this->context();
        $this->travelTo(now()->setDate(2026, 10, 9));
        ClubYearPeriod::where('club_id', $club->id)->update(['starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'name' => 'Season']);
        Payment::create(['club_id' => $club->id, 'amount' => 50, 'status' => 'paid', 'method' => 'bank_import', 'paid_at' => '2026-08-01']);
        Payment::create(['club_id' => $club->id, 'amount' => 20, 'status' => 'paid', 'method' => 'bank_transfer', 'paid_at' => '2026-02-01']);
        $summary = app(ClubFinanceBalanceService::class)->summary($club);
        $this->assertEquals(70, $summary['bank_balance']);
        $this->assertEquals(0, $summary['unassigned_balance']);
        $this->assertEquals(50, $summary['income_period_total']);
        $this->assertSame('Season', $summary['finance_period_label']);
    }

    public function test_team_penalty_payment_and_refund_are_booked_exactly_once(): void
    {
        [$club, $owner] = $this->context();
        $team = Team::factory()->create(['club_id' => $club->id]);
        $fee = TeamFee::create(['team_id' => $team->id, 'user_id' => $owner->id, 'amount' => 5, 'currency' => 'EUR', 'status' => 'open', 'category' => 'penalty']);
        Sanctum::actingAs($owner);
        $url = "/api/v1/teams/{$team->id}/penalty-fees/{$fee->id}";
        $this->postJson($url.'/paid', ['account' => 'cash'])->assertOk();
        $this->postJson($url.'/paid', ['account' => 'cash'])->assertOk();
        $this->assertSame(1, ClubFinanceEntry::where('club_id', $club->id)->count());
        $this->assertNotNull($fee->fresh()->club_finance_entry_id);
        $this->postJson($url.'/cancel')->assertUnprocessable();
        $this->postJson($url.'/refund', ['refunded_on' => '2026-10-09'])->assertOk();
        $this->postJson($url.'/refund', ['refunded_on' => '2026-10-09'])->assertOk();
        $this->assertSame(2, ClubFinanceEntry::where('club_id', $club->id)->count());
        $this->assertEquals(0, app(ClubFinanceBalanceService::class)->summary($club)['cash_balance']);
    }

    public function test_scope_validation_rejects_foreign_teams_and_conflicting_budgets(): void
    {
        [$club, $owner, $period] = $this->context();
        $a = Team::factory()->create(['club_id' => $club->id]);
        $b = Team::factory()->create(['club_id' => $club->id]);
        $budget = $this->budget($club, $period, ['scope_type' => 'team', 'team_id' => $a->id]);
        $service = app(ClubFinanceScopeService::class);
        $resolved = $service->validate(Request::create('/', 'POST', ['club_budget_id' => $budget->id]), $club);
        $this->assertSame($a->id, $resolved['team_id']);
        $this->expectException(ValidationException::class);
        $service->validate(Request::create('/', 'POST', ['club_budget_id' => $budget->id, 'team_id' => $b->id]), $club);
    }

    public function test_foreign_accounts_and_scopes_are_rejected(): void
    {
        [$club, $owner] = $this->context();
        [$foreignClub] = $this->context();
        $team = Team::factory()->create(['club_id' => $foreignClub->id]);
        $foreign = ClubMoneyAccount::create(['club_id' => $foreignClub->id, 'name' => 'Foreign', 'type' => 'cash']);
        $own = ClubMoneyAccount::create(['club_id' => $club->id, 'name' => 'Own', 'type' => 'cash']);
        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/clubs/{$club->id}/money-accounts", ['name' => 'Bad', 'team_id' => $team->id, 'type' => 'cash', 'opening_cents' => 0, 'opened_on' => '2026-01-01'])->assertUnprocessable();
        $this->postJson("/api/v1/clubs/{$club->id}/money-transfers", [
            'from_id' => $own->id, 'to_id' => $foreign->id, 'amount_cents' => 100,
            'booked_on' => '2026-01-01', 'idempotency_key' => 'a58083aa-65d2-42da-bc09-4525bdd8cf62',
        ])->assertUnprocessable();
        $invoice = Invoice::create(['club_id' => $club->id, 'number' => 'AR-1', 'title' => 'Contribution', 'amount' => 100, 'status' => 'open', 'due_date' => '2026-02-01']);
        $this->putJson("/api/v1/clubs/{$club->id}/finance-scopes/invoice/{$invoice->id}", ['team_id' => $team->id])->assertUnprocessable();
        $this->assertSame(0, ClubFinanceEntry::count());
    }

    public function test_pending_migrations_do_not_break_existing_balance_or_team_payment(): void
    {
        [$club, $owner] = $this->context();
        $team = Team::factory()->create(['club_id' => $club->id]);
        $fee = TeamFee::create(['team_id' => $team->id, 'user_id' => $owner->id, 'amount' => 5, 'currency' => 'EUR', 'status' => 'open', 'category' => 'penalty']);
        Schema::table('club_finance_entries', function (Blueprint $table) {
            $table->dropIndex('club_finance_entries_entry_kind_index');
            $table->dropColumn('entry_kind');
        });
        $this->entry($club, $owner, ['type' => 'income', 'account' => 'cash', 'amount' => 20]);
        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/clubs/{$club->id}/finance-workspace")->assertOk()->assertJsonPath('data.available', false);
        $this->postJson("/api/v1/teams/{$team->id}/penalty-fees/{$fee->id}/paid")->assertOk();
        $this->assertEquals(20, app(ClubFinanceBalanceService::class)->summary($club)['cash_balance']);
        $this->assertSame(1, ClubFinanceEntry::count());
    }

    private function context(): array
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $period = ClubYearPeriod::create(['club_id' => $club->id, 'type' => 'business', 'name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);

        return [$club, $owner, $period];
    }

    public function test_historical_payment_assignment_moves_existing_income_without_duplicate_entry(): void
    {
        [$club, $owner] = $this->context();
        $bank = ClubMoneyAccount::create(['club_id' => $club->id, 'name' => 'Bank', 'type' => 'bank']);
        $cash = ClubMoneyAccount::create(['club_id' => $club->id, 'name' => 'Cash', 'type' => 'cash']);
        $payment = Payment::create(['club_id' => $club->id, 'amount' => 50, 'status' => 'paid', 'method' => 'bank_import', 'paid_at' => '2026-02-01']);
        Sanctum::actingAs($owner);
        $url = "/api/v1/clubs/{$club->id}/finance-scopes/payment/{$payment->id}";
        $this->putJson($url, ['club_money_account_id' => $cash->id])->assertUnprocessable();
        $this->putJson($url, ['club_money_account_id' => $bank->id])->assertOk();
        $this->putJson($url, ['club_money_account_id' => $bank->id])->assertOk();
        $accounts = collect($this->getJson("/api/v1/clubs/{$club->id}/finance-workspace")->assertOk()->json('data.accounts'))->keyBy('id');
        $this->assertSame(5000, $accounts[$bank->id]['balance_cents']);
        $this->assertSame(1, Payment::count());
        $this->assertSame(0, ClubFinanceEntry::count());
        $this->assertEquals(50, app(ClubFinanceBalanceService::class)->summary($club)['bank_balance']);
    }

    private function budget(Club $club, ClubYearPeriod $period, array $data = []): ClubBudget
    {
        return ClubBudget::create(array_replace(['club_id' => $club->id, 'club_year_period_id' => $period->id, 'scope_type' => 'club',
            'name' => 'Budget', 'version' => 1, 'approval_status' => 'draft', 'planned_income_cents' => 10000, 'planned_expense_cents' => 10000], $data));
    }

    private function entry(Club $club, User $owner, array $data = []): ClubFinanceEntry
    {
        return ClubFinanceEntry::create(array_replace(['club_id' => $club->id, 'user_id' => $owner->id, 'type' => 'expense',
            'account' => 'bank', 'title' => 'Expense', 'amount' => 10, 'booked_on' => '2026-02-03'], $data));
    }
}
