<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubFinanceEntry;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\ClubYearPeriodReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ClubFinanceYearCloseTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $this->travelTo(now()->setDate(2026, 10, 9));
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $old = $club->yearPeriods()->create(['type' => 'business', 'name' => '2025', 'starts_on' => '2025-01-01', 'ends_on' => '2025-12-31']);
        $next = $club->yearPeriods()->create(['type' => 'business', 'name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        Sanctum::actingAs($owner);

        return [$club, $old, $next, $owner];
    }

    public function test_close_is_idempotent_freezes_report_and_carries_balance_without_new_bookings(): void
    {
        [$club, $old, $next, $owner] = $this->context();
        $account = $this->postJson("/api/v1/clubs/{$club->id}/money-accounts", ['name' => 'Bank', 'type' => 'bank', 'opening_cents' => 10000, 'opened_on' => '2025-01-01'])->assertCreated()->json('data.id');
        $entry = ClubFinanceEntry::create(['club_id' => $club->id, 'user_id' => $owner->id, 'type' => 'expense', 'account' => 'bank', 'club_money_account_id' => $account, 'title' => 'Equipment', 'amount' => 20, 'booked_on' => '2025-10-01']);
        $payment = Payment::create(['club_id' => $club->id, 'amount' => 50, 'status' => 'paid', 'method' => 'bank_import', 'club_money_account_id' => $account, 'paid_at' => '2025-05-01']);
        $invoice = Invoice::create(['club_id' => $club->id, 'user_id' => $owner->id, 'number' => 'OPEN', 'title' => 'Claim', 'amount' => 30, 'status' => 'open', 'issued_at' => '2025-09-01', 'due_date' => '2025-09-15']);
        $payload = ['next_period_id' => $next->id, 'confirmed' => true];
        $stalePeriod = $old->fresh();
        $url = "/api/v1/clubs/{$club->id}/year-periods/{$old->id}/finance-close";
        $this->postJson($url, $payload)->assertOk();
        $this->postJson($url, $payload)->assertOk();
        $old->refresh();
        try {
            $stalePeriod->update(['name' => 'Stale edit']);
            $this->fail('Stale period overwrote a closed year');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->deleteJson("/api/v1/clubs/{$club->id}/year-periods/{$next->id}")->assertUnprocessable();
        $this->assertSame(13000, $old->finance_closing_snapshot['total_cents']);
        $this->assertSame(13000, $old->finance_closing_snapshot['accounts'][0]['balance_cents']);
        $this->assertSame(2, ClubFinanceEntry::count());
        $snapshot = app(ClubYearPeriodReportService::class)->report($club, 'business', $old);
        Payment::create(['club_id' => $club->id, 'invoice_id' => $invoice->id, 'amount' => 30, 'status' => 'paid', 'method' => 'bank_transfer', 'paid_at' => '2026-02-01']);
        $invoice->update(['status' => 'paid']);
        $this->assertSame($snapshot, app(ClubYearPeriodReportService::class)->report($club, 'business', $old->fresh()));
        foreach ([fn () => $entry->update(['amount' => 1]), fn () => $entry->update(['booked_on' => '2026-03-01']), fn () => $entry->delete(), fn () => $payment->update(['amount' => 1]), fn () => $invoice->update(['amount' => 1])] as $write) {
            try {
                $write();
                $this->fail('Closed finance record was changed');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('finance_year', $exception->errors());
            }
        }
        $this->putJson("/api/v1/clubs/{$club->id}/year-periods/{$old->id}", ['type' => 'business', 'name' => 'Moved', 'starts_on' => '2024-01-01', 'ends_on' => '2024-12-31'])->assertUnprocessable();
        $this->postJson("/api/v1/clubs/{$club->id}/money-accounts", ['name' => 'Backdated', 'type' => 'cash', 'opening_cents' => 100, 'opened_on' => '2025-01-01'])->assertUnprocessable();
        $this->assertDatabaseMissing('club_money_accounts', ['name' => 'Backdated']);
    }

    public function test_close_rejects_foreign_period_unconfirmed_and_current_year(): void
    {
        [$club, $old, $next] = $this->context();
        $url = "/api/v1/clubs/{$club->id}/year-periods/{$old->id}/finance-close";
        $this->postJson($url, ['next_period_id' => $next->id, 'confirmed' => false])->assertUnprocessable();
        $foreign = Club::factory()->create(['owner_id' => User::factory()])->yearPeriods()->create(['type' => 'business', 'name' => 'Other', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $this->postJson($url, ['next_period_id' => $foreign->id, 'confirmed' => true])->assertUnprocessable();
        $this->postJson("/api/v1/clubs/{$club->id}/year-periods/{$next->id}/finance-close", ['next_period_id' => $old->id, 'confirmed' => true])->assertUnprocessable();
        Sanctum::actingAs(User::factory()->create());
        $this->postJson($url, ['next_period_id' => $next->id, 'confirmed' => true])->assertForbidden();
        $this->assertNull($old->fresh()->finance_closed_at);
    }
}
