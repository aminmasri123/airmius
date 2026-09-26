<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubNewsletterDelivery;
use App\Models\ClubNewsletterCampaign;
use App\Models\ClubNewsletterSubscription;
use App\Models\User;
use App\Services\ClubNewsletterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubNewsletterTest extends TestCase
{
    use RefreshDatabase;

    public function test_double_opt_in_stores_only_token_hash_and_confirms_subscription(): void
    {
        $owner = User::factory()->create();
        $club = Club::query()->create(['owner_id' => $owner->id, 'name' => 'Mail Club']);

        [$subscription, $token] = app(ClubNewsletterService::class)
            ->requestOptIn($club, 'Member@Example.org', 'Member');

        $this->assertSame('pending', $subscription->status);
        $this->assertSame('member@example.org', $subscription->email);
        $this->assertNotSame($token, $subscription->confirmation_token_hash);
        $this->assertSame(hash('sha256', $token), $subscription->confirmation_token_hash);

        $this->getJson('/api/v1/newsletter/confirm/'.$token)
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $fresh = $subscription->fresh();
        $this->assertSame('confirmed', $fresh->status);
        $this->assertNull($fresh->confirmation_token_hash);
        $this->assertNotNull($fresh->unsubscribe_token_hash);
    }

    public function test_campaign_send_is_club_scoped_idempotent_and_respects_suppression_list(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $club = Club::query()->create(['owner_id' => $owner->id, 'name' => 'Scoped Club']);
        $otherClubOwner = User::factory()->create();
        $otherClub = Club::query()->create(['owner_id' => $otherClubOwner->id, 'name' => 'Other Club']);

        ClubNewsletterSubscription::query()->create([
            'club_id' => $club->id,
            'email' => 'ok@example.org',
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'unsubscribe_token_hash' => hash('sha256', 'ok-token'),
        ]);
        ClubNewsletterSubscription::query()->create([
            'club_id' => $club->id,
            'email' => 'blocked@example.org',
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'unsubscribe_token_hash' => hash('sha256', 'blocked-token'),
        ]);
        ClubNewsletterSubscription::query()->create([
            'club_id' => $otherClub->id,
            'email' => 'foreign@example.org',
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'unsubscribe_token_hash' => hash('sha256', 'foreign-token'),
        ]);

        app(ClubNewsletterService::class)->suppress($club->id, 'blocked@example.org', 'manual');

        Sanctum::actingAs($owner);
        $payload = [
            'subject' => 'Vereinsnews',
            'body' => 'Hallo aus dem Verein.',
            'idempotency_key' => 'newsletter-2026-09',
        ];

        $firstId = $this->postJson("/api/v1/clubs/{$club->id}/newsletter/send", $payload)
            ->assertCreated()
            ->assertJsonPath('data.deliveries_count', 1)
            ->json('data.id');

        $this->postJson("/api/v1/clubs/{$club->id}/newsletter/send", $payload)
            ->assertCreated()
            ->assertJsonPath('data.id', $firstId)
            ->assertJsonPath('data.deliveries_count', 1);

        $this->assertDatabaseHas('club_newsletter_deliveries', [
            'club_id' => $club->id,
            'email' => 'ok@example.org',
            'status' => 'sent',
        ]);
        $this->assertDatabaseMissing('club_newsletter_deliveries', ['email' => 'blocked@example.org']);
        $this->assertDatabaseMissing('club_newsletter_deliveries', ['email' => 'foreign@example.org']);
    }

    public function test_unsubscribe_and_hard_bounce_add_club_scoped_suppression(): void
    {
        $owner = User::factory()->create();
        $club = Club::query()->create(['owner_id' => $owner->id, 'name' => 'Bounce Club']);
        $subscription = ClubNewsletterSubscription::query()->create([
            'club_id' => $club->id,
            'email' => 'bounce@example.org',
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'unsubscribe_token_hash' => hash('sha256', 'unsubscribe-token'),
        ]);

        $unsubscribeToken = app(ClubNewsletterService::class)->unsubscribeToken($subscription);

        $this->getJson('/api/v1/newsletter/unsubscribe/'.$unsubscribeToken)
            ->assertOk()
            ->assertJsonPath('data.status', 'unsubscribed');
        $this->assertDatabaseHas('club_newsletter_suppressions', [
            'club_id' => $club->id,
            'email' => 'bounce@example.org',
            'reason' => 'unsubscribe',
        ]);

        $delivery = ClubNewsletterDelivery::query()->create([
            'club_newsletter_campaign_id' => ClubNewsletterCampaign::query()->create([
                'club_id' => $club->id,
                'created_by' => $owner->id,
                'subject' => 'Bounce',
                'body' => 'Bounce',
                'status' => 'sent',
                'sent_at' => now(),
            ])->id,
            'club_newsletter_subscription_id' => $subscription->id,
            'club_id' => $club->id,
            'email' => 'hard@example.org',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/clubs/{$club->id}/newsletter/deliveries/{$delivery->id}/bounce", [
            'bounce_type' => 'hard',
            'failure_reason' => 'Mailbox unavailable',
        ])->assertOk()->assertJsonPath('data.status', 'bounced');

        $this->assertDatabaseHas('club_newsletter_suppressions', [
            'club_id' => $club->id,
            'email' => 'hard@example.org',
            'reason' => 'hard_bounce',
        ]);
    }
}
