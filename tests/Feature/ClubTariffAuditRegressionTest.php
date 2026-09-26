<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubMembershipType;
use App\Models\Invoice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ClubTariffAuditRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_recurring_invoice_run_snapshots_supported_intervals_and_advances_due_dates(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $payer = User::factory()->create(['name' => 'Familien Zahler']);
        $monthlyMember = User::factory()->create(['name' => 'Monat Mitglied']);
        $quarterlyMember = User::factory()->create(['name' => 'Quartal Mitglied']);
        $onceMember = User::factory()->create(['name' => 'Einmal Mitglied']);
        $club = Club::factory()->create(['owner_id' => $owner->id, 'name' => 'Interval Club']);
        $this->activatePlan($club);
        $membershipType = ClubMembershipType::query()->create([
            'club_id' => $club->id,
            'name' => 'Aktiv',
            'is_active' => true,
            'is_public' => true,
        ]);

        $club->users()->attach($payer->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
            'joined_on' => '2026-01-01',
        ]);
        $club->users()->attach($monthlyMember->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
            'club_membership_type_id' => $membershipType->id,
            'contribution_payer_user_id' => $payer->id,
            'joined_on' => '2026-09-01',
            'contribution_amount' => 12,
            'contribution_interval' => 'monthly',
            'contribution_next_invoice_on' => '2026-09-01',
        ]);
        $club->users()->attach($quarterlyMember->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
            'joined_on' => '2026-10-15',
            'contribution_amount' => 90,
            'contribution_interval' => 'quarterly',
            'contribution_next_invoice_on' => '2026-10-01',
        ]);
        $club->users()->attach($onceMember->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
            'joined_on' => '2026-09-26',
            'contribution_amount' => 25,
            'contribution_interval' => 'once',
            'contribution_next_invoice_on' => '2026-09-26',
        ]);

        $this->artisan('airmius:generate-recurring-contribution-invoices', ['--date' => '2026-10-15'])
            ->assertSuccessful();

        $monthlyInvoice = Invoice::query()->where('membership_user_id', $monthlyMember->id)->firstOrFail();
        $quarterlyInvoice = Invoice::query()->where('membership_user_id', $quarterlyMember->id)->firstOrFail();
        $onceInvoice = Invoice::query()->where('membership_user_id', $onceMember->id)->firstOrFail();

        $this->assertSame($payer->id, $monthlyInvoice->user_id);
        $this->assertSame('12.00', $monthlyInvoice->amount);
        $this->assertSame([
            'membership_type_id' => $membershipType->id,
            'interval' => 'monthly',
            'full_amount' => '12.00',
            'amount' => '12.00',
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'period_days' => 30,
            'billable_days' => 30,
            'active_from' => '2026-09-01',
            'prorated' => false,
            'rounded_cents' => 1200,
        ], collect($monthlyInvoice->contribution_snapshot)->only([
            'membership_type_id',
            'interval',
            'full_amount',
            'amount',
            'period_start',
            'period_end',
            'period_days',
            'billable_days',
            'active_from',
            'prorated',
            'rounded_cents',
        ])->all());

        $this->assertSame('76.30', $quarterlyInvoice->amount);
        $this->assertSame('2026-10-01', $quarterlyInvoice->billing_period_start->toDateString());
        $this->assertSame('2026-12-31', $quarterlyInvoice->billing_period_end->toDateString());
        $this->assertSame(78, $quarterlyInvoice->contribution_snapshot['billable_days']);
        $this->assertTrue($quarterlyInvoice->contribution_snapshot['prorated']);

        $this->assertSame('25.00', $onceInvoice->amount);
        $this->assertSame('2026-09-26', $onceInvoice->billing_period_start->toDateString());
        $this->assertSame('2026-09-26', $onceInvoice->billing_period_end->toDateString());

        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $monthlyMember->id,
            'contribution_next_invoice_on' => '2026-10-01',
        ]);
        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $quarterlyMember->id,
            'contribution_next_invoice_on' => '2027-01-01',
        ]);
        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $onceMember->id,
            'contribution_next_invoice_on' => null,
        ]);
    }

    private function activatePlan(Club $club): void
    {
        $plan = SubscriptionPlan::query()->firstOrCreate(['slug' => 'pro'], [
            'target_actor' => 'verein',
            'name' => 'Pro',
            'monthly_price_cents' => 2990,
            'yearly_price_cents' => 29900,
            'currency' => 'EUR',
            'features' => [],
            'sort_order' => 1,
            'is_public' => true,
            'is_active' => true,
        ]);

        $club->currentSubscription()->updateOrCreate([], [
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'billing_interval' => 'monthly',
        ]);
        $club->unsetRelation('currentSubscription');
    }
}
