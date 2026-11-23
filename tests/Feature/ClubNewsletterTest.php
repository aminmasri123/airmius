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
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubNewsletterTest extends TestCase
{
    use RefreshDatabase;

    public function test_opt_in_uses_configured_system_sender_instead_of_default_smtp(): void
    {
        config(['mail.default' => 'smtp']);
        $this->mock(\App\Support\TransactionalMail::class, function ($mock): void {
            $mock->shouldReceive('transportFor')->once()->with('system')->andReturn([
                'mailer' => 'array', 'address' => 'support@example.org', 'name' => 'Club support',
            ]);
        });
        $club = Club::factory()->create();
        app(ClubNewsletterService::class)->requestOptIn($club, 'reader@example.org', 'Alex');
        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $email = $messages->first()->getOriginalMessage();
        $this->assertSame('support@example.org', $email->getFrom()[0]->getAddress());
        $this->assertStringContainsString('Bitte bestätige deine Anmeldung', $email->getSubject());
        $this->assertStringContainsString('Newsletter-Anmeldung bestätigen', $email->getHtmlBody());
        $this->assertStringContainsString('<a href="http', $email->getHtmlBody());
        $this->assertStringContainsString('Hallo Alex,', $email->getTextBody());
    }

    public function test_subscriber_table_is_paginated_club_scoped_and_hides_tokens(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $other = Club::factory()->create();
        foreach (range(1, 31) as $index) {
            ClubNewsletterSubscription::create([
                'club_id' => $club->id, 'email' => "reader{$index}@example.org",
                'name' => 'Reader '.$index, 'status' => 'pending',
                'confirmation_token_hash' => 'private-confirmation-token-'.$index,
            ]);
        }
        ClubNewsletterSubscription::create(['club_id' => $other->id, 'email' => 'foreign@example.org', 'status' => 'confirmed']);
        Sanctum::actingAs($owner);
        $url = "/api/v1/clubs/{$club->id}/newsletter/subscribers";
        $response = $this->getJson($url)->assertOk()->assertJsonCount(30, 'data')->assertJsonPath('total', 31);
        $response->assertDontSee('private-confirmation-token')->assertDontSee('foreign@example.org');
        $this->assertArrayNotHasKey('confirmation_token_hash', $response->json('data.0'));
        $this->getJson($url.'?subscribers_page=2')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/clubs/{$other->id}/newsletter/subscribers")->assertForbidden();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson($url)->assertForbidden();
    }

    public function test_double_opt_in_stores_only_token_hash_and_confirms_subscription(): void
    {
        Mail::fake();
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

    public function test_public_web_subscription_requires_consent_and_only_accepts_listed_verified_clubs(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $club = Club::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Public Newsletter Club',
            'verification_status' => 'verified',
            'is_listed' => true,
        ]);

        $this->from(route('guest.clubs.show', $club))
            ->post(route('guest.newsletter.subscribe', $club), [
                'email' => 'reader@gmail.com',
                'privacy_consent' => false,
                'website' => '',
            ])
            ->assertRedirect(route('guest.clubs.show', $club))
            ->assertSessionHasErrors('privacy_consent');

        $this->from(route('guest.clubs.show', $club))
            ->post(route('guest.newsletter.subscribe', $club), [
                'email' => 'reader@gmail.com',
                'name' => 'Reader',
                'privacy_consent' => true,
                'website' => '',
            ])
            ->assertRedirect(route('guest.clubs.show', $club))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('club_newsletter_subscriptions', [
            'club_id' => $club->id,
            'email' => 'reader@gmail.com',
            'status' => 'pending',
        ]);

        $club->update(['is_listed' => false]);
        $this->post(route('guest.newsletter.subscribe', $club), [
            'email' => 'other@example.org',
            'privacy_consent' => true,
        ])->assertNotFound();
    }

    public function test_confirmation_link_expires_and_web_status_does_not_leak_tokens(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $club = Club::query()->create(['owner_id' => $owner->id, 'name' => 'Expiry Club']);
        [$subscription, $token] = app(ClubNewsletterService::class)
            ->requestOptIn($club, 'expiry@example.org');
        $subscription->forceFill(['confirmation_sent_at' => now()->subDays(2)])->save();

        $this->get(route('guest.newsletter.confirm', $token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Guest/NewsletterStatus')
                ->where('status', 'invalid'));

        $this->assertSame('pending', $subscription->fresh()->status);
    }

    public function test_public_resubmission_cannot_disable_an_existing_confirmed_subscription(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $club = Club::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Protected Subscription Club',
            'verification_status' => 'verified',
            'is_listed' => true,
        ]);
        $subscription = ClubNewsletterSubscription::query()->create([
            'club_id' => $club->id,
            'email' => 'confirmed@example.org',
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'unsubscribe_token_hash' => hash('sha256', 'existing-token'),
        ]);

        $this->post(route('guest.newsletter.subscribe', $club), [
            'email' => 'confirmed@example.org',
            'privacy_consent' => true,
            'website' => '',
        ])->assertRedirect();

        $fresh = $subscription->fresh();
        $this->assertSame('confirmed', $fresh->status);
        $this->assertNotNull($fresh->confirmed_at);
        $this->assertSame(hash('sha256', 'existing-token'), $fresh->unsubscribe_token_hash);
    }

    public function test_only_authorized_club_roles_can_open_newsletter_management(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $club = Club::query()->create(['owner_id' => $owner->id, 'name' => 'Managed Newsletter Club']);

        $this->actingAs($outsider)
            ->get(route('auth.clubs.newsletter.show', $club))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('auth.clubs.newsletter.show', $club))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/Clubs/Newsletter')
                ->where('club.id', $club->id)
                ->where('counts.confirmed', 0));
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

    public function test_authenticated_postmark_bounce_suppresses_matching_newsletter_only(): void
    {
        config()->set('services.postmark.webhook_username', 'hook');
        config()->set('services.postmark.webhook_password', 'secret');
        config()->set('services.postmark.webhook_ips', []);

        $owner = User::factory()->create();
        $club = Club::query()->create(['owner_id' => $owner->id, 'name' => 'Mail Club']);
        $campaign = ClubNewsletterCampaign::query()->create([
            'club_id' => $club->id, 'created_by' => $owner->id,
            'subject' => 'News', 'body' => 'Text', 'status' => 'sent',
        ]);
        $delivery = ClubNewsletterDelivery::query()->create([
            'club_newsletter_campaign_id' => $campaign->id,
            'club_id' => $club->id,
            'email' => 'member@example.org',
            'status' => 'sent',
            'provider_message_id' => 'provider-123',
        ]);
        $payload = [
            'RecordType' => 'Bounce', 'MessageID' => 'provider-123',
            'Email' => 'member@example.org', 'BouncedAt' => now()->toIso8601String(),
            'Type' => 'HardBounce', 'Inactive' => true,
        ];

        $this->postJson('/webhooks/mail/postmark', $payload)->assertUnauthorized();
        $this->assertSame('sent', $delivery->fresh()->status);

        $this->withBasicAuth('hook', 'secret')
            ->postJson('/webhooks/mail/postmark', [...$payload, 'Email' => 'wrong@example.org'])
            ->assertAccepted()->assertJsonPath('status', 'ignored');
        $this->assertSame('sent', $delivery->fresh()->status);

        $this->postJson('/webhooks/mail/postmark', $payload)
            ->assertAccepted()->assertJsonPath('status', 'processed');
        $this->assertDatabaseHas('club_newsletter_suppressions', [
            'club_id' => $club->id, 'email' => 'member@example.org', 'reason' => 'hard_bounce',
        ]);
        $this->postJson('/webhooks/mail/postmark', $payload)
            ->assertAccepted()->assertJsonPath('status', 'duplicate');
    }
}
