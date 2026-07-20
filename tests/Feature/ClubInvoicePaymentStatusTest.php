<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Invoice;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubInvoicePaymentStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_club_membership_web_exposes_payment_status_labels_and_options(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['name' => 'Status Member']);
        $club = Club::factory()->create(['owner_id' => $owner->id]);

        $club->users()->attach($member->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]);

        foreach (Invoice::PAYMENT_STATUSES as $status) {
            $this->createMembershipInvoice($club, $member, $status);
        }

        $this->actingAs($owner)
            ->get(route('auth.club-memberships.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/ClubMemberships/Index')
                ->where('invoiceStatusOptions.0.value', 'open')
                ->where('invoiceStatusOptions.0.label', 'Offen')
                ->where('invoiceStatusOptions.1.value', 'paid')
                ->where('invoiceStatusOptions.1.label', 'Bezahlt')
                ->where('invoiceStatusOptions.2.value', 'overdue')
                ->where('invoiceStatusOptions.2.label', 'Überfällig')
                ->where('invoiceStatusOptions.3.value', 'cancelled')
                ->where('invoiceStatusOptions.3.label', 'Storniert')
                ->where('clubs.0.invoice_summary.total_count', 4)
                ->where('clubs.0.invoice_summary.open_count', 2)
                ->where('clubs.0.invoice_summary.paid_count', 1)
                ->where('clubs.0.invoice_summary.cancelled_count', 1)
                ->where('clubs.0.invoice_summary.open_amount', 50)
                ->has('clubs.0.invoices', 4)
                ->where('clubs.0.invoices.0.status', 'cancelled')
                ->where('clubs.0.invoices.0.status_label', 'Storniert')
                ->where('clubs.0.invoices.1.status', 'overdue')
                ->where('clubs.0.invoices.1.status_label', 'Überfällig')
                ->where('clubs.0.invoices.2.status', 'paid')
                ->where('clubs.0.invoices.2.status_label', 'Bezahlt')
                ->where('clubs.0.invoices.3.status', 'open')
                ->where('clubs.0.invoices.3.status_label', 'Offen')
            );
    }

    public function test_club_billing_api_exposes_payment_status_labels_and_options(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);

        $club->users()->attach($member->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]);

        $this->createMembershipInvoice($club, $member, 'overdue');
        $this->createMembershipInvoice($club, $member, 'cancelled');

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/clubs/{$club->id}/billing")
            ->assertOk()
            ->assertJsonPath('data.invoice_status_options.0.value', 'open')
            ->assertJsonPath('data.invoice_status_options.0.label', 'Offen')
            ->assertJsonPath('data.invoice_status_options.1.value', 'paid')
            ->assertJsonPath('data.invoice_status_options.1.label', 'Bezahlt')
            ->assertJsonPath('data.invoice_status_options.2.value', 'overdue')
            ->assertJsonPath('data.invoice_status_options.2.label', 'Überfällig')
            ->assertJsonPath('data.invoice_status_options.3.value', 'cancelled')
            ->assertJsonPath('data.invoice_status_options.3.label', 'Storniert')
            ->assertJsonPath('data.invoice_summary.total_count', 2)
            ->assertJsonPath('data.invoice_summary.open_count', 1)
            ->assertJsonPath('data.invoice_summary.cancelled_count', 1)
            ->assertJsonPath('data.invoice_summary.open_amount', 25)
            ->assertJsonPath('data.invoices.data.0.status', 'cancelled')
            ->assertJsonPath('data.invoices.data.0.status_label', 'Storniert')
            ->assertJsonPath('data.invoices.data.1.status', 'overdue')
            ->assertJsonPath('data.invoices.data.1.status_label', 'Überfällig');
    }

    public function test_member_billing_overview_is_available_in_web_and_api(): void
    {
        $user = User::factory()->create();
        $club = Club::factory()->create([
            'name' => 'Billing Club',
            'owner_id' => $user->id,
        ]);

        $this->createMembershipInvoice($club, $user, 'open', 10);
        $this->createMembershipInvoice($club, $user, 'paid', 20);
        $this->createMembershipInvoice($club, $user, 'overdue', 30);
        $this->createMembershipInvoice($club, $user, 'cancelled', 40);
        $plan = SubscriptionPlan::query()->create([
            'slug' => 'test-pro',
            'target_actor' => 'sportler',
            'name' => 'Test Pro',
            'monthly_price_cents' => 5000,
            'yearly_price_cents' => 50000,
            'currency' => 'EUR',
            'features' => [],
            'sort_order' => 1,
            'is_public' => false,
            'is_active' => true,
        ]);

        SubscriptionInvoice::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'number' => 'SUB-2026-0001',
            'title' => 'Airmius Pro',
            'amount_cents' => 5000,
            'currency' => 'EUR',
            'status' => 'paid',
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'SUB-2026-0001',
            'issued_at' => now()->addHour(),
            'due_at' => now()->addDays(14),
            'paid_at' => now()->addHour(),
        ]);

        $this->actingAs($user)
            ->get(route('auth.settings', ['tab' => 'billing']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/Settings/Index')
                ->where('billingHistory.summary.total_count', 5)
                ->where('billingHistory.summary.club_invoice_count', 4)
                ->where('billingHistory.summary.subscription_invoice_count', 1)
                ->where('billingHistory.summary.open_count', 2)
                ->where('billingHistory.summary.paid_count', 2)
                ->where('billingHistory.summary.cancelled_count', 1)
                ->where('billingHistory.summary.open_amount', 40)
                ->where('billingHistory.summary.paid_amount', 70)
                ->where('billingHistory.invoices.0.status', 'cancelled')
                ->where('billingHistory.invoices.0.status_label', 'Storniert')
            );

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.billing_history.summary.total_count', 5)
            ->assertJsonPath('data.billing_history.summary.open_count', 2)
            ->assertJsonPath('data.billing_history.summary.paid_amount', 70)
            ->assertJsonPath('data.billing_history.invoices.0.status_label', 'Storniert')
            ->assertJsonPath('data.billing_history.subscription_invoices.0.status_label', 'Bezahlt');

        $this->getJson('/api/v1/billing/invoices')
            ->assertOk()
            ->assertJsonPath('meta.summary.total_count', 5)
            ->assertJsonPath('meta.summary.open_count', 2)
            ->assertJsonPath('meta.summary.paid_amount', 70)
            ->assertJsonPath('data.0.status_label', 'Bezahlt');
    }

    private function createMembershipInvoice(Club $club, User $member, string $status, int $amount = 25): Invoice
    {
        return Invoice::query()->create([
            'club_id' => $club->id,
            'user_id' => $member->id,
            'number' => 'INV-'.$club->id.'-'.$status,
            'title' => 'Mitgliedsbeitrag '.$status,
            'amount' => $amount,
            'status' => $status,
            'source' => 'manual',
            'due_date' => now()->addDays(7),
            'issued_at' => now(),
            'paid_at' => $status === 'paid' ? now() : null,
        ]);
    }
}
