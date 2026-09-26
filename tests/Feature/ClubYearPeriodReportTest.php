<?php

namespace Tests\Feature;

use App\Models\BankTransaction;
use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Models\ClubFinanceEntry;
use App\Models\ClubYearPeriod;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubYearPeriodReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_report_filters_by_frozen_assignment_and_separates_unassigned_history(): void
    {
        [$owner, $club] = $this->club();
        $legacyInvoice = $this->invoice($club, 'ALT-1', 'manual');
        $legacyBank = $this->bank($club, 'legacy', 40);
        $this->financeEntry($club, 'Altbeleg', 'income', 30);
        $period = $this->period($club, 'business');
        $currentInvoice = $this->invoice($club, 'NEU-1', 'manual');
        $currentBank = $this->bank($club, 'current', -15);
        $this->financeEntry($club, 'Gebühr', 'expense', 12);
        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/clubs/{$club->id}/year-periods/report?type=business&period_id={$period->id}")
            ->assertOk()
            ->assertJsonPath('data.selection', 'period')
            ->assertJsonPath('data.period.id', $period->id)
            ->assertJsonPath('data.summary.invoices.total_count', 1)
            ->assertJsonPath('data.summary.bank_transactions.total_count', 1)
            ->assertJsonPath('data.summary.bank_transactions.debit_amount', 15)
            ->assertJsonPath('data.summary.finance_entries.total_count', 1)
            ->assertJsonPath('data.summary.finance_entries.expense_amount', 12)
            ->assertJsonPath('data.unassigned.invoices.total_count', 1)
            ->assertJsonPath('data.unassigned.bank_transactions.total_count', 1)
            ->assertJsonPath('data.unassigned.bank_transactions.credit_amount', 40)
            ->assertJsonPath('data.unassigned.finance_entries.total_count', 1)
            ->assertJsonPath('data.unassigned.finance_entries.income_amount', 30);

        $this->assertNull($legacyInvoice->business_year_period_id);
        $this->assertNull($legacyBank->business_year_period_id);
        $this->assertSame($period->id, $currentInvoice->business_year_period_id);
        $this->assertSame($period->id, $currentBank->business_year_period_id);
    }

    public function test_contribution_and_sport_reports_use_only_their_own_period_type(): void
    {
        [$owner, $club] = $this->club();
        $legacyContribution = $this->invoice($club, 'ALT-B', 'recurring_contribution');
        $legacyEvent = $this->event($club, 'Alttraining', 'training');
        $contribution = $this->period($club, 'contribution');
        $sport = $this->period($club, 'sport');
        $this->invoice($club, 'NEU-B', 'recurring_contribution');
        $this->invoice($club, 'MANUELL', 'manual');
        $this->event($club, 'Ligaspiel', 'match');
        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/clubs/{$club->id}/year-periods/report?type=contribution&period_id={$contribution->id}")
            ->assertOk()
            ->assertJsonPath('data.summary.invoices.total_count', 1)
            ->assertJsonPath('data.unassigned.invoices.total_count', 1);

        $this->getJson("/api/v1/clubs/{$club->id}/year-periods/report?type=sport&period_id={$sport->id}")
            ->assertOk()
            ->assertJsonPath('data.summary.events.total_count', 1)
            ->assertJsonPath('data.summary.events.by_type.match', 1)
            ->assertJsonPath('data.unassigned.events.total_count', 1)
            ->assertJsonPath('data.unassigned.events.by_type.training', 1);

        $this->assertNull($legacyContribution->contribution_year_period_id);
        $this->assertNull($legacyEvent->sport_year_period_id);
    }

    public function test_unassigned_filter_is_explicit_and_cross_club_or_wrong_type_periods_are_rejected(): void
    {
        [$owner, $club] = $this->club();
        $this->invoice($club, 'ALT-1', 'manual');
        $business = $this->period($club, 'business');
        [, $foreignClub] = $this->club();
        $foreign = $this->period($foreignClub, 'business');
        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/clubs/{$club->id}/year-periods/report?type=business&period_id=unassigned")
            ->assertOk()
            ->assertJsonPath('data.selection', 'unassigned')
            ->assertJsonPath('data.period', null)
            ->assertJsonPath('data.summary.invoices.total_count', 1);

        $this->getJson("/api/v1/clubs/{$club->id}/year-periods/report?type=contribution&period_id={$business->id}")
            ->assertNotFound();
        $this->getJson("/api/v1/clubs/{$club->id}/year-periods/report?type=business&period_id={$foreign->id}")
            ->assertNotFound();
        $this->getJson("/api/v1/clubs/{$club->id}/year-periods/report?type=business&period_id=invalid")
            ->assertUnprocessable();
    }

    public function test_financial_period_reports_require_finance_permission(): void
    {
        [$owner, $club] = $this->club();
        $member = User::factory()->create();
        $club->users()->attach($member->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        $period = $this->period($club, 'business');

        Sanctum::actingAs($member);
        $this->getJson("/api/v1/clubs/{$club->id}/year-periods/report?type=business&period_id={$period->id}")
            ->assertForbidden();

        $club->users()->updateExistingPivot($member->id, [
            'permission_overrides' => ['finance.view' => true],
        ]);
        $this->getJson("/api/v1/clubs/{$club->id}/year-periods")
            ->assertOk()
            ->assertJsonPath('data.can_manage', false)
            ->assertJsonPath('data.can_view_reports', true);
        $this->getJson("/api/v1/clubs/{$club->id}/year-periods/report?type=business&period_id={$period->id}")
            ->assertOk();

        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/clubs/{$club->id}/year-periods/report?type=business&period_id={$period->id}")
            ->assertOk();
    }

    public function test_report_exposes_catalog_membership_development_and_role_reports(): void
    {
        [$owner, $club] = $this->club();
        $period = $this->period($club, 'business');
        $linkedUser = User::factory()->create();
        $joiner = User::factory()->create();
        $leaver = User::factory()->create();
        $steady = User::factory()->create();
        $secondSteady = User::factory()->create();

        $club->users()->attach($linkedUser->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active', 'joined_on' => '2025-01-01']);
        $club->users()->attach($joiner->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active', 'joined_on' => '2026-02-01']);
        $club->users()->attach($leaver->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'former', 'joined_on' => '2024-01-01', 'membership_ended_at' => '2026-08-31 12:00:00']);
        $club->users()->attach($steady->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active', 'joined_on' => '2024-01-01']);
        $club->users()->attach($secondSteady->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active', 'joined_on' => '2024-01-01']);
        ClubExternalMember::query()->create([
            'club_id' => $club->id,
            'linked_user_id' => $linkedUser->id,
            'email' => 'linked@example.test',
            'membership_status' => 'active',
            'joined_on' => '2025-01-01',
        ]);
        ClubExternalMember::query()->create([
            'club_id' => $club->id,
            'email' => 'external@example.test',
            'membership_status' => 'active',
            'joined_on' => '2026-06-01',
        ]);

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/clubs/{$club->id}/year-periods/report?type=business&period_id={$period->id}")
            ->assertOk()
            ->assertJsonPath('data.catalog.privacy_min_group_size', 5)
            ->assertJsonPath('data.catalog.metrics.0.key', 'membership_development.people_total')
            ->assertJsonPath('data.membership_development.suppressed', false)
            ->assertJsonPath('data.membership_development.people_total', 7)
            ->assertJsonPath('data.membership_development.membership_rows_total', 8)
            ->assertJsonPath('data.membership_development.multiple_membership_rows', 1)
            ->assertJsonPath('data.membership_development.joined_people_count', 2)
            ->assertJsonPath('data.membership_development.ended_people_count', 1)
            ->assertJsonPath('data.standard_reports.board.label', 'Vorstand')
            ->assertJsonPath('data.standard_reports.assembly.label', 'Mitgliederversammlung')
            ->assertJsonPath('data.standard_reports.funder.permission', 'finance.export')
            ->assertJsonPath('data.standard_reports.association_cutoff.permission', 'members.export');
    }

    public function test_membership_development_is_suppressed_below_privacy_minimum(): void
    {
        [$owner, $club] = $this->club();
        $period = $this->period($club, 'business');
        $member = User::factory()->create();
        $club->users()->attach($member->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active', 'joined_on' => '2026-01-01']);

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/clubs/{$club->id}/year-periods/report?type=business&period_id={$period->id}")
            ->assertOk()
            ->assertJsonPath('data.membership_development.suppressed', true)
            ->assertJsonPath('data.membership_development.reason', 'privacy_min_group_size')
            ->assertJsonPath('data.membership_development.observed_group_size', 2);
    }

    private function club(): array
    {
        $owner = User::factory()->create();

        return [$owner, Club::factory()->create(['owner_id' => $owner->id])];
    }

    private function period(Club $club, string $type): ClubYearPeriod
    {
        return ClubYearPeriod::query()->create([
            'club_id' => $club->id,
            'type' => $type,
            'name' => ucfirst($type).' 2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);
    }

    private function invoice(Club $club, string $number, string $source): Invoice
    {
        return Invoice::query()->create([
            'club_id' => $club->id,
            'number' => $number,
            'title' => 'Beitrag',
            'amount' => 25,
            'status' => 'open',
            'source' => $source,
            'billing_period_start' => '2026-04-01',
            'due_date' => '2026-04-15 00:00:00',
            'issued_at' => '2026-04-01 10:00:00',
        ]);
    }

    private function bank(Club $club, string $hash, float $amount): BankTransaction
    {
        return BankTransaction::query()->create([
            'club_id' => $club->id,
            'transaction_hash' => hash('sha256', $hash),
            'booking_date' => '2026-05-01',
            'amount' => $amount,
            'currency' => 'EUR',
        ]);
    }

    private function event(Club $club, string $title, string $type): Event
    {
        return Event::query()->create([
            'club_id' => $club->id,
            'title' => $title,
            'type' => $type,
            'visibility' => 'organization',
            'status' => 'scheduled',
            'start_time' => '2026-08-01 18:00:00',
        ]);
    }

    private function financeEntry(Club $club, string $title, string $type, float $amount): ClubFinanceEntry
    {
        return ClubFinanceEntry::query()->create([
            'club_id' => $club->id,
            'type' => $type,
            'account' => 'bank',
            'category' => 'period_report_test',
            'title' => $title,
            'amount' => $amount,
            'booked_on' => '2026-05-02',
        ]);
    }
}
