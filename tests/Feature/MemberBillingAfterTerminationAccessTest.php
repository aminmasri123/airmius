<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberBillingAfterTerminationAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_keeps_personal_invoice_access_after_membership_ended_without_management_rights(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $otherMember = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, [
            'role' => 'member',
            'membership_status' => 'active',
            'joined_on' => '2026-01-01',
        ]);
        $club->users()->attach($otherMember->id, [
            'role' => 'member',
            'membership_status' => 'active',
        ]);

        $ownInvoice = Invoice::query()->create([
            'club_id' => $club->id,
            'user_id' => $member->id,
            'number' => 'T20-14-OWN',
            'title' => 'Offener Beitrag vor Austritt',
            'amount' => '36.00',
            'status' => 'open',
            'issued_at' => '2026-10-09',
            'due_date' => '2026-10-31',
            'billing_period_start' => '2026-01-01',
            'billing_period_end' => '2026-12-31',
        ]);
        $foreignInvoice = Invoice::query()->create([
            'club_id' => $club->id,
            'user_id' => $otherMember->id,
            'number' => 'T20-14-FOREIGN',
            'title' => 'Fremder Beitrag',
            'amount' => '24.00',
            'status' => 'open',
            'issued_at' => '2026-10-09',
            'due_date' => '2026-10-31',
        ]);

        $club->users()->updateExistingPivot($member->id, [
            'membership_status' => 'ended',
            'membership_ends_on' => '2026-10-09',
            'membership_ended_at' => now(),
        ]);

        Sanctum::actingAs($member);

        $this->getJson('/api/v1/billing/invoices')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownInvoice->id)
            ->assertJsonPath('data.0.status', 'open')
            ->assertJsonPath('data.0.outstanding_amount', '36.00');

        $this->getJson("/api/v1/billing/invoices/{$ownInvoice->id}?kind=club_invoice")
            ->assertOk()
            ->assertJsonPath('data.id', $ownInvoice->id)
            ->assertJsonPath('data.number', 'T20-14-OWN');

        $this->get("/api/v1/billing/invoices/club_invoice/{$ownInvoice->id}/download")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->getJson("/api/v1/billing/invoices/{$foreignInvoice->id}?kind=club_invoice")
            ->assertNotFound();
        $this->getJson("/api/v1/billing/invoices/club_invoice/{$foreignInvoice->id}/download")
            ->assertNotFound();

        $this->postJson("/api/v1/clubs/{$club->id}/membership-invoices/{$ownInvoice->id}/payments", [
            'amount' => '36.00',
            'method' => 'cash',
            'paid_at' => '2026-10-09',
        ])->assertForbidden();
    }
}
