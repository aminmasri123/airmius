<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentBookingReceipt;
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

    public function test_external_member_invoice_accepts_and_exposes_a_payment(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $this->activatePlan($club);
        $externalMember = ClubExternalMember::query()->create([
            'club_id' => $club->id,
            'created_by' => $owner->id,
            'name' => 'Max Extern',
            'email' => 'max.extern@example.org',
            'membership_status' => 'active',
        ]);
        $invoice = Invoice::query()->create([
            'club_id' => $club->id,
            'club_external_member_id' => $externalMember->id,
            'number' => 'EXT-PAY-100',
            'title' => 'Mitgliedsbeitrag',
            'amount' => '42.00',
            'status' => 'open',
            'due_date' => now()->addWeek(),
            'issued_at' => now(),
        ]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/clubs/{$club->id}/membership-invoices/{$invoice->id}/payments", [
            'amount' => '10.00',
            'partial_payment' => true,
            'method' => 'cash',
            'paid_at' => now()->toDateString(),
        ])->assertOk()
            ->assertJsonPath('data.invoices.0.status', 'open')
            ->assertJsonPath('data.invoices.0.outstanding_amount', '32.00')
            ->assertJsonPath('data.payments.0.user_id', null)
            ->assertJsonPath('data.payments.0.club_external_member_id', $externalMember->id)
            ->assertJsonPath('data.payments.0.external_member.name', 'Max Extern');

        $this->postJson("/api/v1/clubs/{$club->id}/membership-invoices/{$invoice->id}/payments", [
            'partial_payment' => false,
            'method' => 'cash',
            'paid_at' => now()->toDateString(),
        ])->assertOk()
            ->assertJsonPath('data.invoices.0.status', 'paid')
            ->assertJsonPath('data.invoices.0.outstanding_amount', '0.00');

        $payments = Payment::query()->orderBy('id')->get();
        $this->assertCount(2, $payments);
        $this->assertNull($payments->first()->user_id);
        $this->assertSame($externalMember->id, $payments->first()->club_external_member_id);
        $this->assertSame(['10.00', '32.00'], $payments->pluck('amount')->all());
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(
            [$externalMember->id, $externalMember->id],
            PaymentBookingReceipt::query()->orderBy('id')->pluck('club_external_member_id')->all(),
        );
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
            'payment_method' => 'sepa',
            'sepa_iban' => 'DE89370400440532013000',
            'sepa_mandate_reference' => 'MANDATE-2026-1',
            'sepa_mandate_signed_on' => '2026-01-01',
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

    public function test_invoice_run_succeeds_when_recipient_notification_fails(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $this->activatePlan($club);
        $externalMember = ClubExternalMember::query()->create([
            'club_id' => $club->id,
            'created_by' => $owner->id,
            'name' => 'Externes Mitglied',
            'email' => 'external@example.org',
            'membership_status' => 'active',
            'joined_on' => '2026-01-01',
            'contribution_amount' => 36,
            'contribution_interval' => 'yearly',
            'payment_method' => 'bank_transfer',
        ]);

        Notification::swap(new class
        {
            public function route(string $channel, string $recipient): never
            {
                throw new \RuntimeException("{$channel} transport unavailable for {$recipient}.");
            }
        });

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/clubs/{$club->id}/membership-invoice-runs", [
            'run_date' => '2026-01-01',
            'due_date' => '2026-01-15',
        ])
            ->assertCreated()
            ->assertJsonPath('invoice_run.created_count', 1);

        $this->assertDatabaseHas('invoices', [
            'club_id' => $club->id,
            'club_external_member_id' => $externalMember->id,
            'amount' => '36.00',
        ]);
    }

    public function test_automatic_run_uses_inferred_dates_and_includes_external_members_once(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $this->activatePlan($club);
        $externalMember = ClubExternalMember::query()->create([
            'club_id' => $club->id,
            'created_by' => $owner->id,
            'name' => 'Automatik Extern',
            'email' => 'automatic@example.org',
            'membership_status' => 'active',
            'joined_on' => '2026-01-01',
            'contribution_amount' => 12,
            'contribution_interval' => 'monthly',
            'contribution_next_invoice_on' => null,
        ]);

        $this->artisan('airmius:generate-recurring-contribution-invoices', ['--date' => '2026-01-01'])
            ->assertSuccessful();
        $this->artisan('airmius:generate-recurring-contribution-invoices', ['--date' => '2026-01-01'])
            ->assertSuccessful();

        $this->assertSame(1, Invoice::query()
            ->where('club_external_member_id', $externalMember->id)
            ->whereDate('billing_period_start', '2026-01-01')
            ->count());
    }

    public function test_invoice_run_blocks_missing_transfer_email_and_incomplete_sepa_mandate(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $this->activatePlan($club);
        $club->users()->attach($member->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
            'joined_on' => '2026-01-01',
            'contribution_amount' => 36,
            'contribution_interval' => 'yearly',
            'payment_method' => 'sepa',
            'sepa_iban' => null,
            'sepa_mandate_active' => false,
        ]);
        ClubExternalMember::query()->create([
            'club_id' => $club->id,
            'created_by' => $owner->id,
            'name' => 'Ohne E-Mail',
            'email' => '',
            'membership_status' => 'active',
            'joined_on' => '2026-01-01',
            'contribution_amount' => 12,
            'contribution_interval' => 'yearly',
        ]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/clubs/{$club->id}/membership-invoice-runs/preview", [
            'run_date' => '2026-10-07',
        ])
            ->assertOk()
            ->assertJsonPath('data.billable_count', 0)
            ->assertJsonPath('data.skipped_count', 2)
            ->assertJsonPath('data.rows.0.skip_reason', 'missing_recipient')
            ->assertJsonPath('data.rows.1.skip_reason', 'missing_recipient');
    }

    public function test_new_external_member_keeps_selected_sepa_payment_method(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $this->activatePlan($club);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/clubs/{$club->id}/members/bulk", [
            'send_invitation' => false,
            'members' => [[
                'name' => 'SEPA Extern',
                'email' => 'new-sepa@example.org',
                'membership_status' => 'active',
                'contribution_amount' => 24,
                'contribution_interval' => 'yearly',
                'payment_method' => 'sepa_debit',
                'sepa_iban' => 'DE89370400440532013000',
                'sepa_mandate_reference' => 'MANDATE-EXT-1',
                'sepa_mandate_signed_on' => '2026-01-01',
                'sepa_mandate_active' => true,
            ]],
        ])->assertCreated();

        $this->assertDatabaseHas('club_external_members', [
            'club_id' => $club->id,
            'email' => 'new-sepa@example.org',
            'payment_method' => 'sepa_debit',
            'sepa_mandate_active' => true,
        ]);

        $this->postJson("/api/v1/clubs/{$club->id}/membership-invoice-runs/preview", [
            'run_date' => '2026-01-01',
        ])
            ->assertOk()
            ->assertJsonPath('data.billable_count', 1)
            ->assertJsonPath('data.direct_debit_count', 1);
    }

    public function test_explicit_transfer_selection_ignores_stale_sepa_details(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $this->activatePlan($club);
        ClubExternalMember::query()->create([
            'club_id' => $club->id,
            'created_by' => $owner->id,
            'name' => 'Transfer Mitglied',
            'email' => 'transfer-selection@example.org',
            'membership_status' => 'active',
            'joined_on' => '2026-01-01',
            'contribution_amount' => 24,
            'contribution_interval' => 'yearly',
            'payment_method' => 'bank_transfer',
            'sepa_iban' => 'DE89370400440532013000',
            'sepa_mandate_reference' => 'OLD-MANDATE',
            'sepa_mandate_signed_on' => '2026-01-01',
            'sepa_mandate_active' => true,
        ]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/clubs/{$club->id}/membership-invoice-runs/preview", [
            'run_date' => '2026-01-01',
        ])
            ->assertOk()
            ->assertJsonPath('data.billable_count', 1)
            ->assertJsonPath('data.direct_debit_count', 0)
            ->assertJsonPath('data.transfer_count', 1);
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
