<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\Payment;
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
}
