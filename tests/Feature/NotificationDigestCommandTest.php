<?php

namespace Tests\Feature;

use App\Models\MailDelivery;
use App\Models\Notification;
use App\Models\User;
use App\Notifications\NotificationDigest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\TestCase;

class NotificationDigestCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_digest_sends_unread_notifications_once_and_tracks_them(): void
    {
        NotificationFacade::fake();
        $user = User::factory()->create([
            'email' => 'digest@example.test',
            'language' => 'en',
            'notification_channels' => ['email' => true],
        ]);
        $first = Notification::query()->create([
            'user_id' => $user->id,
            'type' => 'club.announcement',
            'data' => ['title' => 'Training', 'body' => 'Neue Zeit'],
            'read' => false,
        ]);
        $second = Notification::query()->create([
            'user_id' => $user->id,
            'type' => 'chat.message',
            'data' => ['title' => 'Chat', 'body' => 'Neue Nachricht'],
            'read' => false,
        ]);

        $this->artisan('airmius:send-notification-digests')
            ->assertExitCode(0)
            ->expectsOutputToContain('Versendet: 1');

        NotificationFacade::assertSentTo($user, NotificationDigest::class);
        $this->assertNotNull($first->fresh()->email_digest_sent_at);
        $this->assertNotNull($second->fresh()->email_digest_sent_at);
        $this->assertDatabaseHas('mail_deliveries', [
            'recipient_id' => $user->id,
            'mail_type' => 'notification.digest',
            'status' => 'sent',
        ]);

        $this->artisan('airmius:send-notification-digests')
            ->assertExitCode(0)
            ->expectsOutputToContain('Versendet: 0');

        $this->assertSame(1, MailDelivery::query()->where('mail_type', 'notification.digest')->count());
    }

    public function test_disabled_email_digest_keeps_notifications_unmarked(): void
    {
        NotificationFacade::fake();
        $user = User::factory()->create([
            'email' => 'disabled@example.test',
            'notification_channels' => ['email' => false],
        ]);
        $notification = Notification::query()->create([
            'user_id' => $user->id,
            'type' => 'club.announcement',
            'data' => ['title' => 'Verein', 'body' => 'Info'],
            'read' => false,
        ]);

        $this->artisan('airmius:send-notification-digests')
            ->assertExitCode(0)
            ->expectsOutputToContain('Versendet: 0');

        NotificationFacade::assertNothingSent();
        $this->assertNull($notification->fresh()->email_digest_sent_at);
    }
}
