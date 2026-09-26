<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubDepartment;
use App\Models\ClubMembershipRequest;
use App\Models\ClubMembershipType;
use App\Models\CommerceOrder;
use App\Models\CommerceRefund;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberPortalOverviewApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_portal_combines_only_the_authenticated_members_own_records(): void
    {
        $member = User::factory()->create();
        $other = User::factory()->create();
        $club = Club::factory()->create(['name' => 'Airmius SV', 'owner_id' => $member->id]);
        $foreignClub = Club::factory()->create(['name' => 'Foreign SV', 'owner_id' => $other->id]);
        $type = ClubMembershipType::query()->create([
            'club_id' => $club->id,
            'name' => 'Adult',
            'is_public' => true,
            'is_active' => true,
        ]);
        $plan = SubscriptionPlan::query()->where('slug', 'starter')->firstOrFail();
        $department = ClubDepartment::query()->create([
            'club_id' => $club->id,
            'name' => 'Running',
            'is_public' => true,
        ]);
        $club->users()->updateExistingPivot($member->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
            'club_membership_type_id' => $type->id,
            'club_department_id' => $department->id,
            'member_number' => 'M-100',
            'contribution_amount' => '12.50',
            'contribution_interval' => 'monthly',
            'payment_method' => 'sepa',
            'sepa_mandate_active' => true,
        ]);
        $foreignClub->users()->updateExistingPivot($other->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]);

        $invoice = Invoice::query()->create([
            'club_id' => $club->id,
            'user_id' => $member->id,
            'number' => 'INV-100',
            'title' => 'Membership',
            'amount' => '42.00',
            'status' => 'open',
            'issued_at' => now(),
            'due_date' => now()->addWeek(),
        ]);
        Invoice::query()->create([
            'club_id' => $foreignClub->id,
            'user_id' => $other->id,
            'number' => 'INV-FOREIGN',
            'title' => 'Hidden',
            'amount' => '99.00',
            'status' => 'open',
            'issued_at' => now(),
            'due_date' => now()->addWeek(),
        ]);
        Payment::query()->create([
            'club_id' => $club->id,
            'user_id' => $member->id,
            'invoice_id' => $invoice->id,
            'purpose' => 'Membership',
            'amount' => '10.00',
            'status' => 'paid',
            'method' => 'cash',
            'paid_at' => now(),
        ]);
        SubscriptionInvoice::query()->create([
            'user_id' => $member->id,
            'club_id' => $club->id,
            'subscription_plan_id' => $plan->id,
            'number' => 'SUB-100',
            'title' => 'Airmius',
            'amount_cents' => 1900,
            'currency' => 'EUR',
            'status' => 'paid',
            'issued_at' => now(),
        ]);
        ClubMembershipRequest::query()->create([
            'club_id' => $club->id,
            'user_id' => $member->id,
            'club_membership_type_id' => $type->id,
            'club_department_id' => $department->id,
            'type' => 'termination',
            'status' => 'pending',
            'requested_termination_on' => now()->addMonth()->toDateString(),
        ]);
        ClubMembershipRequest::query()->create([
            'club_id' => $foreignClub->id,
            'user_id' => $other->id,
            'type' => 'termination',
            'status' => 'pending',
        ]);
        $order = CommerceOrder::query()->create([
            'user_id' => $member->id,
            'club_id' => $club->id,
            'type' => 'marketplace',
            'provider' => 'stripe',
            'amount_cents' => 5000,
            'currency' => 'EUR',
            'status' => 'paid',
        ]);
        CommerceRefund::query()->create([
            'commerce_order_id' => $order->id,
            'requested_by' => $member->id,
            'idempotency_key' => str_repeat('a', 64),
            'amount_cents' => 1200,
            'currency' => 'EUR',
            'provider' => 'stripe',
            'status' => 'succeeded',
        ]);

        Sanctum::actingAs($member);

        $this->getJson('/api/v1/portal')->assertOk()
            ->assertJsonPath('data.memberships.0.club.name', 'Airmius SV')
            ->assertJsonPath('data.memberships.0.tariff.name', 'Adult')
            ->assertJsonPath('data.memberships.0.department.name', 'Running')
            ->assertJsonPath('data.billing.invoices.0.number', 'INV-100')
            ->assertJsonPath('data.billing.payments.0.amount', '10.00')
            ->assertJsonPath('data.billing.subscription_invoices.0.number', 'SUB-100')
            ->assertJsonPath('data.requests.0.type', 'termination')
            ->assertJsonPath('data.refunds.0.amount_cents', 1200)
            ->assertJsonPath('data.cancellations.0.status', 'pending')
            ->assertJsonMissing(['INV-FOREIGN', 'Foreign SV']);
    }

    public function test_portal_detail_endpoints_are_paginated_and_keep_member_boundaries(): void
    {
        $member = User::factory()->create();
        $other = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $member->id]);

        foreach (range(1, 3) as $index) {
            Invoice::query()->create([
                'club_id' => $club->id,
                'user_id' => $member->id,
                'number' => 'INV-'.$index,
                'amount' => '10.00',
                'status' => 'open',
                'issued_at' => now()->subDays($index),
                'due_date' => now()->addDays($index),
            ]);
        }
        Invoice::query()->create([
            'club_id' => $club->id,
            'user_id' => $other->id,
            'number' => 'INV-HIDDEN',
            'amount' => '99.00',
            'status' => 'open',
            'issued_at' => now(),
            'due_date' => now()->addWeek(),
        ]);

        Sanctum::actingAs($member);

        $this->getJson('/api/v1/portal/invoices?per_page=2')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('total', 3)
            ->assertJsonMissing(['INV-HIDDEN']);
    }
}
