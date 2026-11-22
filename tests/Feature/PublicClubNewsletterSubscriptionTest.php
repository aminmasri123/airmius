<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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
            'email' => 'reader@example.org',
            'name' => 'Reader',
            'privacy_consent' => true,
            'website' => '',
        ])->assertAccepted()
            ->assertJsonPath('message', 'newsletter_confirmation_required')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('club_newsletter_subscriptions', [
            'club_id' => $club->id,
            'email' => 'reader@example.org',
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
            'email' => 'reader@example.org',
            'privacy_consent' => true,
            'website' => '',
        ])->assertNotFound();
    }
}
