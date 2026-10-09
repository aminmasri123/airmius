<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\ClubBusinessPartner;
use App\Models\ClubExternalMember;
use App\Models\Payment;
use App\Models\Sponsor;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubDonationSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_money_donation_records_restriction_campaign_and_audit_without_invoice_context(): void
    {
        Notification::fake();
        [$club, $owner, $member] = $this->clubWithFinanceMember();
        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/clubs/{$club->id}/donations", [
            'user_id' => $member->id,
            'amount' => 42.50,
            'method' => 'bank_transfer',
            'reference' => 'BANK-2026-09',
            'donation_type' => 'money',
            'donation_restriction' => 'Jugendtraining',
            'donation_campaign' => 'Sommerfest 2026',
            'notes' => 'Barrierefreier Zugang',
        ])->assertOk();

        $payment = Payment::query()->firstOrFail();

        $this->assertSame($club->id, $payment->club_id);
        $this->assertSame($member->id, $payment->user_id);
        $this->assertNull($payment->invoice_id);
        $this->assertSame('donation', $payment->purpose);
        $this->assertSame('money', $payment->donation_type);
        $this->assertSame('Jugendtraining', $payment->donation_restriction);
        $this->assertSame('Sommerfest 2026', $payment->donation_campaign);

        $this->assertDatabaseHas('activities', [
            'club_id' => $club->id,
            'user_id' => $owner->id,
            'type' => 'club.donation.recorded',
            'subject_type' => Payment::class,
            'subject_id' => $payment->id,
        ]);
    }

    public function test_donation_route_rejects_sponsoring_or_membership_contribution_context(): void
    {
        [$club, $owner, $member] = $this->clubWithFinanceMember();
        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/clubs/{$club->id}/donations", [
            'user_id' => $member->id,
            'amount' => 15,
            'donation_restriction' => 'Sponsoring Trikot',
        ])->assertUnprocessable()->assertJsonValidationErrors('donation_restriction');

        $this->postJson("/api/v1/clubs/{$club->id}/donations", [
            'user_id' => $member->id,
            'amount' => 15,
            'donation_campaign' => 'Mitgliedsbeitrag 2026',
        ])->assertUnprocessable()->assertJsonValidationErrors('donation_campaign');

        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, Activity::query()->where('type', 'club.donation.recorded')->count());
    }

    public function test_donation_user_must_belong_to_same_club(): void
    {
        [$club, $owner] = $this->clubWithFinanceMember();
        $otherMember = User::factory()->create();
        $otherClub = Club::factory()->create(['owner_id' => User::factory()]);
        $otherClub->users()->attach($otherMember->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]);
        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/clubs/{$club->id}/donations", [
            'user_id' => $otherMember->id,
            'amount' => 25,
            'donation_type' => 'in_kind',
            'donation_restriction' => 'Materiallager',
        ])->assertNotFound();

        $this->assertSame(0, Payment::query()->count());
    }

    private function clubWithFinanceMember(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);

        $club->users()->attach($member->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]);

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

        return [$club, $owner, $member];
    }

    public function test_external_members_partners_and_sponsors_can_donate_without_an_account(): void
    {
        Notification::fake();
        [$club, $owner] = $this->clubWithFinanceMember();
        Sanctum::actingAs($owner);
        $external = ClubExternalMember::create(['club_id' => $club->id,
            'name' => 'External Donor', 'email' => 'external@example.org']);
        $partner = ClubBusinessPartner::create(['club_id' => $club->id,
            'name' => 'Partner Donor', 'contact' => ['email' => 'partner@example.org'], 'is_active' => true]);
        $sponsor = Sponsor::create(['club_id' => $club->id, 'name' => 'Sponsor Donor', 'email' => 'sponsor@example.org']);
        foreach ([
            ['external_member', 'club_external_member_id', $external],
            ['partner', 'club_business_partner_id', $partner],
            ['sponsor', 'sponsor_id', $sponsor],
        ] as [$type, $field, $source]) {
            $response = $this->postJson("/api/v1/clubs/{$club->id}/donations", [
                'donor_type' => $type, $field => $source->id, 'amount' => 20, 'method' => 'cash',
            ])->assertOk();
            $payment = Payment::latest('id')->firstOrFail();
            $this->assertNull($payment->user_id);
            $this->assertSame($source->id, $payment->$field);
            $this->assertSame($source->name, $payment->donor_snapshot['name']);
            $this->assertSame('donation', $payment->purpose);
            $this->assertNull($payment->invoice_id);
            $this->assertContains($type.':'.$source->id,
                array_column($response->json('data.donor_options'), 'key'));
        }
    }

    public function test_other_donor_is_saved_without_creating_a_member_and_keeps_receipt_details(): void
    {
        [$club, $owner] = $this->clubWithFinanceMember();
        Sanctum::actingAs($owner);
        $response = $this->postJson("/api/v1/clubs/{$club->id}/donations", [
            'donor_type' => 'other', 'donor_name' => 'Guest Donor',
            'donor_email' => 'guest@example.org', 'donor_address' => 'Example Street 12',
            'amount' => 25, 'method' => 'bank_transfer',
        ])->assertOk();
        $payment = Payment::firstOrFail();
        $this->assertNull($payment->user_id);
        $this->assertNull($payment->club_external_member_id);
        $this->assertSame('Guest Donor', $payment->donor_snapshot['name']);
        $this->assertSame('Example Street 12', $payment->donor_snapshot['address']);
        $response->assertJsonPath('data.payments.0.donor_snapshot.name', 'Guest Donor');
        $this->assertDatabaseCount('club_external_members', 0);
    }

    public function test_foreign_external_members_partners_and_sponsors_are_rejected(): void
    {
        [$club, $owner] = $this->clubWithFinanceMember();
        $otherClub = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $external = ClubExternalMember::create(['club_id' => $otherClub->id,
            'name' => 'Foreign', 'email' => 'foreign@example.org']);
        $partner = ClubBusinessPartner::create(['club_id' => $otherClub->id, 'name' => 'Foreign', 'is_active' => true]);
        $sponsor = Sponsor::create(['club_id' => $otherClub->id, 'name' => 'Foreign']);
        Sanctum::actingAs($owner);
        foreach ([
            ['external_member', 'club_external_member_id', $external->id],
            ['partner', 'club_business_partner_id', $partner->id],
            ['sponsor', 'sponsor_id', $sponsor->id],
        ] as [$type, $field, $id]) {
            $this->postJson("/api/v1/clubs/{$club->id}/donations", [
                'donor_type' => $type, $field => $id, 'amount' => 20,
            ])->assertNotFound();
        }
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_invalid_donor_details_and_future_dates_do_not_create_payments(): void
    {
        [$club, $owner, $member] = $this->clubWithFinanceMember();
        Sanctum::actingAs($owner);
        foreach ([
            [['donor_type' => 'other'], 'donor_name'],
            [['donor_type' => 'member'], 'user_id'],
            [['donor_type' => 'other', 'donor_name' => 'Guest', 'user_id' => $member->id], 'donor_type'],
            [['donor_type' => 'other', 'donor_name' => 'Guest', 'donor_email' => 'invalid'], 'donor_email'],
            [['user_id' => $member->id, 'paid_at' => now()->addDay()->toDateString()], 'paid_at'],
        ] as [$payload, $field]) {
            $this->postJson("/api/v1/clubs/{$club->id}/donations", ['amount' => 10, ...$payload])
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_partner_name_snapshot_survives_partner_rename_and_deletion(): void
    {
        [$club, $owner] = $this->clubWithFinanceMember();
        Sanctum::actingAs($owner);
        $partner = ClubBusinessPartner::create(['club_id' => $club->id, 'name' => 'Original Name', 'is_active' => true]);
        $this->postJson("/api/v1/clubs/{$club->id}/donations", [
            'donor_type' => 'partner', 'club_business_partner_id' => $partner->id, 'amount' => 10,
        ])->assertOk();
        $partner->update(['name' => 'New Name']);
        $partner->delete();
        $payment = Payment::firstOrFail();
        $this->assertNull($payment->club_business_partner_id);
        $this->assertSame('Original Name', $payment->donor_snapshot['name']);
    }

    public function test_member_without_finance_permission_cannot_record_donations(): void
    {
        [$club, , $member] = $this->clubWithFinanceMember();
        Sanctum::actingAs($member);
        $this->postJson("/api/v1/clubs/{$club->id}/donations", [
            'donor_type' => 'other', 'donor_name' => 'Guest', 'amount' => 10,
        ])->assertForbidden();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_donor_identity_cannot_be_reassigned_during_payment_correction(): void
    {
        [$club, $owner, $member] = $this->clubWithFinanceMember();
        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/clubs/{$club->id}/donations", [
            'donor_type' => 'other', 'donor_name' => 'Guest', 'amount' => 10,
        ])->assertOk();
        $payment = Payment::firstOrFail();
        $this->putJson("/api/v1/clubs/{$club->id}/payments/{$payment->id}", [
            'amount' => 10, 'user_id' => $member->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->putJson("/api/v1/clubs/{$club->id}/payments/{$payment->id}", [
            'amount' => 12, 'method' => 'cash',
        ])->assertOk();
        $this->assertNull($payment->fresh()->user_id);
        $this->assertSame('Guest', $payment->fresh()->donor_snapshot['name']);
        $this->assertSame('12.00', $payment->fresh()->amount);
    }
}
