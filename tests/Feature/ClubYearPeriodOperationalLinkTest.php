<?php

namespace Tests\Feature;

use App\Http\Resources\Api\V1\EventResource;
use App\Models\BankTransaction;
use App\Models\Club;
use App\Models\ClubFinanceEntry;
use App\Models\ClubYearPeriod;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubYearPeriodOperationalLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_operations_are_linked_without_reinterpreting_existing_records(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $legacyInvoice = $this->invoice($club, 'ALT-1', 'recurring_contribution');
        $legacyBank = $this->bank($club, 'legacy');
        $legacyEntry = $this->financeEntry($club, 'Altbeleg');
        $legacyEvent = $this->event($club, 'Alttermin');

        $business = $this->period($club, 'business');
        $contribution = $this->period($club, 'contribution');
        $sport = $this->period($club, 'sport');

        $invoice = $this->invoice($club, 'NEU-1', 'recurring_contribution');
        $manualInvoice = $this->invoice($club, 'NEU-2', 'manual');
        $bank = $this->bank($club, 'new');
        $entry = $this->financeEntry($club, 'Neuer Beleg');
        $event = $this->event($club, 'Neuer Termin');

        $this->assertNull($legacyInvoice->business_year_period_id);
        $this->assertNull($legacyInvoice->contribution_year_period_id);
        $this->assertNull($legacyBank->business_year_period_id);
        $this->assertNull($legacyEntry->business_year_period_id);
        $this->assertNull($legacyEvent->sport_year_period_id);
        $this->assertSame($business->id, $invoice->business_year_period_id);
        $this->assertSame($contribution->id, $invoice->contribution_year_period_id);
        $this->assertSame($business->id, $manualInvoice->business_year_period_id);
        $this->assertNull($manualInvoice->contribution_year_period_id);
        $this->assertSame($business->id, $bank->business_year_period_id);
        $this->assertSame($business->id, $entry->business_year_period_id);
        $this->assertSame($sport->id, $event->sport_year_period_id);
    }

    public function test_period_assignment_is_frozen_when_operational_dates_change(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $business = $this->period($club, 'business');
        $sport = $this->period($club, 'sport');
        $invoice = $this->invoice($club, 'FEST-1', 'manual');
        $event = $this->event($club, 'Fest zugeordnet');
        $entry = $this->financeEntry($club, 'Fest zugeordnet');

        $invoice->update(['issued_at' => '2028-01-01 10:00:00']);
        $event->update(['start_time' => '2028-01-01 10:00:00']);
        $entry->update(['booked_on' => '2028-01-01']);

        $this->assertSame($business->id, $invoice->refresh()->business_year_period_id);
        $this->assertSame($sport->id, $event->refresh()->sport_year_period_id);
        $this->assertSame($business->id, $entry->refresh()->business_year_period_id);
    }

    public function test_linked_period_cannot_be_deleted(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $business = $this->period($club, 'business');
        $this->financeEntry($club, 'Geschützter Beleg');
        Sanctum::actingAs($owner);

        $this->deleteJson("/api/v1/clubs/{$club->id}/year-periods/{$business->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('year_period');
        $this->assertDatabaseHas('club_year_periods', ['id' => $business->id]);
    }

    public function test_period_references_are_visible_in_finance_and_event_details(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $business = $this->period($club, 'business');
        $contribution = $this->period($club, 'contribution');
        $sport = $this->period($club, 'sport');
        $invoice = $this->invoice($club, 'SICHTBAR-1', 'recurring_contribution');
        $bank = $this->bank($club, 'visible');
        $entry = $this->financeEntry($club, 'Sichtbarer Beleg');
        $event = $this->event($club, 'Sichtbarer Termin')->load('sportYearPeriod');
        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/clubs/{$club->id}/billing")
            ->assertOk()
            ->assertJsonPath('data.invoices.data.0.business_year_period.name', $business->name)
            ->assertJsonPath('data.invoices.data.0.contribution_year_period.name', $contribution->name);

        $this->assertSame($business->name, $invoice->load('businessYearPeriod')->businessYearPeriod?->name);
        $this->assertSame($business->name, $bank->load('businessYearPeriod')->businessYearPeriod?->name);
        $this->assertSame($business->name, $entry->load('businessYearPeriod')->businessYearPeriod?->name);
        $this->assertSame($sport->name, (new EventResource($event))->resolve(request())['sport_year_period']['name']);
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
            'billing_period_end' => '2027-03-31',
            'due_date' => '2026-04-15 00:00:00',
            'issued_at' => '2026-04-01 10:00:00',
        ]);
    }

    private function bank(Club $club, string $hash): BankTransaction
    {
        return BankTransaction::query()->create([
            'club_id' => $club->id,
            'transaction_hash' => hash('sha256', $hash),
            'booking_date' => '2026-05-01',
            'amount' => 25,
            'currency' => 'EUR',
        ]);
    }

    private function event(Club $club, string $title): Event
    {
        return Event::query()->create([
            'club_id' => $club->id,
            'title' => $title,
            'type' => 'training',
            'visibility' => 'organization',
            'start_time' => '2026-08-01 18:00:00',
        ]);
    }

    private function financeEntry(Club $club, string $title): ClubFinanceEntry
    {
        return ClubFinanceEntry::query()->create([
            'club_id' => $club->id,
            'type' => 'income',
            'account' => 'bank',
            'category' => 'membership_fee',
            'title' => $title,
            'amount' => 25,
            'booked_on' => '2026-05-02',
        ]);
    }
}
