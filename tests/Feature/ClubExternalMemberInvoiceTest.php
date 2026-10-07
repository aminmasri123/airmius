<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Models\Invoice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\ClubInvoiceCreated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubExternalMemberInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_member_can_receive_manual_invoice_by_email(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $this->activatePlan($club);

        $externalMember = ClubExternalMember::query()->create([
            'club_id' => $club->id,
            'created_by' => $owner->id,
            'name' => 'Externes Mitglied',
            'email' => 'external@example.org',
            'membership_status' => 'active',
            'contribution_amount' => 42,
            'contribution_interval' => 'yearly',
        ]);

        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/v1/clubs/{$club->id}/external-members/{$externalMember->id}/invoices", [
            'title' => 'Mitgliedsbeitrag',
            'amount' => '42.00',
            'due_date' => '2026-10-31',
            'description' => 'Jahresbeitrag',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.invoices.0.club_external_member_id', $externalMember->id)
            ->assertJsonPath('data.invoices.0.member.is_external', true)
            ->assertJsonPath('data.invoices.0.member.email', 'external@example.org');

        $invoice = Invoice::query()->firstOrFail();
        $this->assertNull($invoice->user_id);
        $this->assertNull($invoice->membership_user_id);
        $this->assertSame($externalMember->id, $invoice->club_external_member_id);
        $this->assertSame('42.00', $invoice->amount);

        Notification::assertSentOnDemand(ClubInvoiceCreated::class);
    }

    public function test_contribution_invoice_run_previews_and_creates_due_member_invoices(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $member = User::factory()->create(['name' => 'SEPA Mitglied', 'email' => 'sepa@example.org']);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $this->activatePlan($club);

        $club->users()->attach($member->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
            'joined_on' => '2026-01-01',
            'contribution_amount' => 36,
            'contribution_interval' => 'yearly',
            'contribution_next_invoice_on' => '2026-01-01',
            'payment_method' => 'sepa',
            'sepa_iban' => 'DE89370400440532013000',
            'sepa_mandate_active' => true,
        ]);

        $externalMember = ClubExternalMember::query()->create([
            'club_id' => $club->id,
            'created_by' => $owner->id,
            'name' => 'Extern Ueberweisung',
            'email' => 'transfer@example.org',
            'membership_status' => 'active',
            'joined_on' => '2026-01-01',
            'contribution_amount' => 12,
            'contribution_interval' => 'monthly',
            'contribution_next_invoice_on' => '2026-01-01',
        ]);

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/clubs/{$club->id}/membership-invoice-runs/preview", [
            'run_date' => '2026-01-01',
            'due_date' => '2026-01-15',
        ])
            ->assertOk()
            ->assertJsonPath('data.billable_count', 2)
            ->assertJsonPath('data.direct_debit_count', 1)
            ->assertJsonPath('data.transfer_count', 1)
            ->assertJsonPath('data.total_amount', '48.00');

        $this->postJson("/api/v1/clubs/{$club->id}/membership-invoice-runs", [
            'run_date' => '2026-01-01',
            'due_date' => '2026-01-15',
        ])
            ->assertCreated()
            ->assertJsonPath('invoice_run.created_count', 2);

        $this->assertDatabaseHas('invoices', [
            'club_id' => $club->id,
            'membership_user_id' => $member->id,
            'amount' => '36.00',
            'claim_status' => 'awaiting_direct_debit',
        ]);
        $this->assertDatabaseHas('invoices', [
            'club_id' => $club->id,
            'club_external_member_id' => $externalMember->id,
            'amount' => '12.00',
            'claim_status' => 'awaiting_transfer',
        ]);

        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $member->id,
            'contribution_next_invoice_on' => '2027-01-01',
        ]);
        $this->assertSame('2026-02-01', $externalMember->fresh()->contribution_next_invoice_on->toDateString());

        $this->postJson("/api/v1/clubs/{$club->id}/membership-invoice-runs", [
            'run_date' => '2026-01-01',
            'due_date' => '2026-01-15',
        ])
            ->assertCreated()
            ->assertJsonPath('invoice_run.created_count', 0);

        $this->assertSame(2, Invoice::query()->where('club_id', $club->id)->count());
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
