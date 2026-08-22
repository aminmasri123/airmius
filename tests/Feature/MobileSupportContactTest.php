<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class MobileSupportContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_send_structured_support_contact(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        Event::fake([MessageSending::class]);

        $this->postJson('/api/v1/support/contact', [
            'name' => 'Manipulierter Absender',
            'email' => 'spoofed@example.test',
            'subject' => 'Mitgliedschaft kann nicht geöffnet werden',
            'category' => 'membership',
            'priority' => 'high',
            'platform' => 'android',
            'message' => 'Beim Öffnen der Mitgliedschaft erscheint eine Fehlermeldung.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.sent', true)
            ->assertJsonPath('data.category', 'membership');

        Event::assertDispatched(
            MessageSending::class,
            function (MessageSending $event) use ($user): bool {
                $replyTo = $event->message->getReplyTo();
                $body = (string) $event->message->getTextBody();

                return ($replyTo[0]?->getAddress() ?? null) === $user->email
                    && str_contains($body, $user->name)
                    && ! str_contains($body, 'Manipulierter Absender');
            }
        );
    }

    public function test_support_contact_requires_authentication_and_valid_content(): void
    {
        $this->postJson('/api/v1/support/contact', [
            'name' => 'Nicht angemeldet',
            'email' => 'guest@example.test',
            'message' => 'Hilfe',
        ])->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/support/contact', [
            'name' => '',
            'email' => 'invalid',
            'message' => '',
        ])->assertUnprocessable();
    }

    public function test_guest_can_send_public_interest_contact_without_authentication(): void
    {
        Event::fake([MessageSending::class]);

        $this->postJson('/api/v1/public/contact', [
            'name' => 'Gast Verein',
            'email' => 'guest@example.test',
            'subject' => 'Interesse an Sponsoring',
            'category' => 'sponsoring',
            'message' => 'Bitte senden Sie mir Informationen zu einer Partnerschaft.',
            'privacy_consent' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.sent', true)
            ->assertJsonPath('data.category', 'sponsoring');

        Event::assertDispatched(
            MessageSending::class,
            fn (MessageSending $event): bool => ($event->message->getReplyTo()[0]?->getAddress() ?? null) === 'guest@example.test'
                && str_contains((string) $event->message->getTextBody(), 'Gast Verein')
        );

        $this->assertDatabaseHas('public_contact_requests', [
            'user_id' => null,
            'email' => 'guest@example.test',
            'category' => 'sponsoring',
            'status' => 'new',
            'email_delivery_status' => 'sent',
        ]);
        $this->assertDatabaseMissing('public_contact_requests', [
            'email' => 'guest@example.test',
            'privacy_consent_at' => null,
        ]);
    }

    public function test_public_contact_is_preserved_when_notification_email_fails(): void
    {
        Mail::shouldReceive('raw')
            ->once()
            ->andThrow(new RuntimeException('SMTP temporarily unavailable'));

        $this->postJson('/api/v1/public/contact', [
            'name' => 'Airmius QA Gast',
            'email' => 'qa-standort@airmius.test',
            'subject' => 'Standort vorschlagen',
            'category' => 'location_club',
            'platform' => 'android',
            'message' => 'Name: Airmius QA Laufpark\nSichtbarkeit: false',
            'privacy_consent' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.sent', true)
            ->assertJsonPath('data.category', 'location_club');

        $this->assertDatabaseHas('public_contact_requests', [
            'email' => 'qa-standort@airmius.test',
            'category' => 'location_club',
            'platform' => 'android',
            'status' => 'new',
            'email_delivery_status' => 'failed',
        ]);
    }
}
