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

    public function test_confirmation_email_has_a_professional_html_button_and_clickable_fallback_link(): void
    {
        $url = 'https://airmius.com/newsletter/bestaetigen/test-token';
        $html = view('emails.club-newsletter-confirmation', [
            'subscriberName' => 'Anna Beispiel',
            'clubName' => 'Verein Test',
            'confirmationUrl' => $url,
            'expiresHours' => 24,
        ])->render();
        $text = view('emails.club-newsletter-confirmation-text', [
            'subscriberName' => 'Anna Beispiel',
            'clubName' => 'Verein Test',
            'confirmationUrl' => $url,
            'expiresHours' => 24,
        ])->render();

        $this->assertStringContainsString('Newsletter-Anmeldung bestätigen', $html);
        $this->assertSame(2, substr_count($html, 'href="'.$url.'"'));
        $this->assertStringContainsString('Hallo Anna Beispiel,', $html);
        $this->assertStringContainsString($url, $text);
    }

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

        $this->travel(4)->minutes();
        $this->postJson("/api/v1/public/clubs/{$club->id}/newsletter", $payload)
            ->assertAccepted()
            ->assertJsonPath('data.confirmation_sent', true)
            ->assertJsonPath('data.retry_after_seconds', 300);
        $this->assertNotSame($firstHash, ClubNewsletterSubscription::query()->sole()->confirmation_token_hash);

        $this->travel(5)->minutes();
        $this->postJson("/api/v1/public/clubs/{$club->id}/newsletter", $payload)
            ->assertAccepted()
            ->assertJsonPath('data.confirmation_sent', true)
            ->assertJsonPath('data.support_required', true);

        $this->travel(5)->minutes();
        $this->postJson("/api/v1/public/clubs/{$club->id}/newsletter", $payload)
            ->assertAccepted()
            ->assertJsonPath('data.confirmation_sent', false)
            ->assertJsonPath('data.support_required', true);
        $this->assertSame(3, ClubNewsletterSubscription::query()->sole()->metadata['confirmation_attempts']);
        Carbon::setTestNow();
    }

    public function test_confirmation_transport_failure_is_reported_to_the_mobile_app(): void
    {
        $this->freezeSecond();
        Mail::shouldReceive('mailer')->once()->andThrow(new \RuntimeException('Transport unavailable'));
        $club = Club::factory()->create(['verification_status' => 'verified', 'is_listed' => true]);

        $this->postJson("/api/v1/public/clubs/{$club->id}/newsletter", [
            'email' => 'reader@gmail.com',
            'privacy_consent' => true,
        ])->assertAccepted()
            ->assertJsonPath('data.confirmation_sent', false)
            ->assertJsonPath('data.confirmation_delivery_failed', true)
            ->assertJsonPath('data.retry_after_seconds', 300);
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

    public function test_verified_email_owner_can_unsubscribe_from_a_legacy_subscription(): void
    {
        $user = User::factory()->create(['email' => 'reader@gmail.com']);
        $club = Club::factory()->create(['verification_status' => 'verified', 'is_listed' => false]);
        $subscription = ClubNewsletterSubscription::query()->create([
            'club_id' => $club->id,
            'email' => $user->email,
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/clubs/{$club->id}/newsletter/status")
            ->assertOk()->assertJsonPath('data.status', 'confirmed');
        $url = "/api/v1/clubs/{$club->id}/newsletter/subscription";
        $this->deleteJson($url)->assertOk()->assertJsonPath('data.status', 'unsubscribed');
        $this->deleteJson($url)->assertOk()->assertJsonPath('data.status', 'unsubscribed');

        $this->assertNotNull($subscription->fresh()->unsubscribed_at);
        $this->assertDatabaseHas('club_newsletter_suppressions', [
            'club_id' => $club->id,
            'email' => $user->email,
            'reason' => 'unsubscribe',
        ]);
    }

    public function test_unverified_email_does_not_grant_control_of_a_legacy_subscription(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'reader@gmail.com']);
        $club = Club::factory()->create(['verification_status' => 'verified', 'is_listed' => true]);
        $subscription = ClubNewsletterSubscription::query()->create([
            'club_id' => $club->id,
            'email' => $user->email,
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
        $url = "/api/v1/clubs/{$club->id}/newsletter/subscription";
        $this->deleteJson($url)->assertUnauthorized();
        Sanctum::actingAs($user);
        $this->deleteJson($url)->assertOk()->assertJsonPath('data.status', 'none');

        $this->assertSame('confirmed', $subscription->fresh()->status);
    }
}
