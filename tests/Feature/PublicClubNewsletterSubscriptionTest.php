<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubNewsletterSubscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublicClubNewsletterSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_mobile_endpoint_starts_double_opt_in_for_a_listed_verified_club(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $club = Club::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Öffentlicher Newsletterverein',
            'verification_status' => 'verified',
            'is_listed' => true,
        ]);

        $this->postJson("/api/v1/public/clubs/{$club->id}/newsletter", [
            'email' => 'reader@gmail.com',
            'name' => 'Reader',
            'privacy_consent' => true,
            'website' => '',
        ])->assertAccepted()
            ->assertJsonPath('message', 'newsletter_confirmation_required')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('club_newsletter_subscriptions', [
            'club_id' => $club->id,
            'email' => 'reader@gmail.com',
            'status' => 'pending',
        ]);
    }

    public function test_public_mobile_endpoint_rejects_an_unlisted_club(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $club = Club::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Privater Newsletterverein',
            'verification_status' => 'verified',
            'is_listed' => false,
        ]);

        $this->postJson("/api/v1/public/clubs/{$club->id}/newsletter", [
            'email' => 'reader@gmail.com',
            'privacy_consent' => true,
            'website' => '',
        ])->assertNotFound();
    }

    public function test_repeated_pending_request_is_cooldown_protected(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-11-22 10:00:00');
        $club = Club::factory()->create([
            'verification_status' => 'verified',
            'is_listed' => true,
        ]);
        $payload = [
            'email' => 'reader@gmail.com',
            'privacy_consent' => true,
            'website' => '',
        ];

        $this->postJson("/api/v1/public/clubs/{$club->id}/newsletter", $payload)
            ->assertAccepted()
            ->assertJsonPath('data.confirmation_sent', true);
        $firstHash = ClubNewsletterSubscription::query()->sole()->confirmation_token_hash;

        Carbon::setTestNow('2026-11-22 10:01:00');
        $this->postJson("/api/v1/public/clubs/{$club->id}/newsletter", $payload)
            ->assertAccepted()
            ->assertJsonPath('message', 'newsletter_confirmation_pending')
            ->assertJsonPath('data.confirmation_sent', false);

        $this->assertSame($firstHash, ClubNewsletterSubscription::query()->sole()->confirmation_token_hash);
        Carbon::setTestNow();
    }

    public function test_authenticated_user_can_read_own_subscription_status(): void
    {
        $user = User::factory()->create(['email' => 'reader@gmail.com']);
        $club = Club::factory()->create([
            'verification_status' => 'verified',
            'is_listed' => true,
        ]);
        ClubNewsletterSubscription::query()->create([
            'club_id' => $club->id,
            'email' => 'reader@gmail.com',
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/clubs/{$club->id}/newsletter/status")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');
    }
}
