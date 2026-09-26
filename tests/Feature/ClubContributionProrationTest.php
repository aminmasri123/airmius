<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubMembershipRequest;
use App\Models\ClubMembershipType;
use App\Models\Invoice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ClubContributionProrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_under_year_entry_generates_prorated_snapshot_once(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $member = User::factory()->create(['name' => 'Teiljahr Mitglied']);
        $club = Club::factory()->create(['owner_id' => $owner->id, 'name' => 'Proration Club']);
        $this->activatePlan($club);

        $club->users()->attach($member->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
            'joined_on' => '2026-07-15',
            'contribution_amount' => 120,
            'contribution_interval' => 'yearly',
            'contribution_next_invoice_on' => '2026-01-01',
        ]);

        $this->artisan('airmius:generate-recurring-contribution-invoices', ['--date' => '2026-07-15'])
            ->assertSuccessful();

        $invoice = Invoice::query()->where('membership_user_id', $member->id)->firstOrFail();
        $this->assertSame('55.89', $invoice->amount);
        $this->assertSame('2026-01-01', $invoice->billing_period_start->toDateString());
        $this->assertSame('2026-12-31', $invoice->billing_period_end->toDateString());
        $this->assertSame([
            'version' => 1,
            'membership_user_id' => $member->id,
            'payer_user_id' => $member->id,
            'membership_type_id' => null,
            'interval' => 'yearly',
            'full_amount' => '120.00',
            'amount' => '55.89',
            'period_start' => '2026-01-01',
            'period_end' => '2026-12-31',
            'period_days' => 365,
            'billable_days' => 170,
            'active_from' => '2026-07-15',
            'prorated' => true,
            'rounded_cents' => 5589,
        ], collect($invoice->contribution_snapshot)->except('captured_at')->all());

        $this->artisan('airmius:generate-recurring-contribution-invoices', ['--date' => '2026-07-15'])
            ->assertSuccessful();

        $this->assertSame(1, Invoice::query()->where('membership_user_id', $member->id)->count());
    }

    public function test_membership_change_updates_future_tariff_without_rewriting_existing_invoice(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $this->activatePlan($club);
        $basic = ClubMembershipType::query()->create([
            'club_id' => $club->id,
            'name' => 'Basis',
            'is_active' => true,
            'is_public' => true,
        ]);
        $premium = ClubMembershipType::query()->create([
            'club_id' => $club->id,
            'name' => 'Premium',
            'is_active' => true,
            'is_public' => true,
        ]);
        $club->contributionRules()->create([
            'club_membership_type_id' => $premium->id,
            'name' => 'Premium 30',
            'valid_from' => '2026-01-01',
            'billing_interval' => 'monthly',
            'amount' => 30,
            'factor_key' => 'standard',
            'is_active' => true,
        ]);
        $club->users()->attach($member->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
            'club_membership_type_id' => $basic->id,
            'joined_on' => '2026-01-01',
            'contribution_amount' => 20,
            'contribution_interval' => 'monthly',
            'contribution_next_invoice_on' => '2026-09-01',
        ]);

        $this->artisan('airmius:generate-recurring-contribution-invoices', ['--date' => '2026-09-01'])
            ->assertSuccessful();
        $september = Invoice::query()->where('membership_user_id', $member->id)->firstOrFail();
        $this->assertSame('20.00', $september->amount);

        $change = ClubMembershipRequest::query()->create([
            'club_id' => $club->id,
            'user_id' => $member->id,
            'club_membership_type_id' => $premium->id,
            'type' => 'membership_change',
            'status' => 'pending',
            'application_data' => [],
            'accepted_documents' => [],
            'preview_amount' => 30,
            'preview_interval' => 'monthly',
        ]);

        $this->actingAs($owner)
            ->post(route('auth.club-membership-requests.approve', $change))
            ->assertRedirect();

        $this->assertSame('20.00', $september->fresh()->amount);

        $this->artisan('airmius:generate-recurring-contribution-invoices', ['--date' => '2026-10-01'])
            ->assertSuccessful();

        $october = Invoice::query()
            ->where('membership_user_id', $member->id)
            ->whereDate('billing_period_start', '2026-10-01')
            ->firstOrFail();

        $this->assertSame('30.00', $october->amount);
        $this->assertSame(30, $october->contribution_snapshot['rounded_cents'] / 100);
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
