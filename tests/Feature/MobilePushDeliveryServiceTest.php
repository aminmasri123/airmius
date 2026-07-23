<?php

namespace Tests\Feature;

use App\Models\MobileDeviceToken;
use App\Models\MobilePushDelivery;
use App\Models\Notification;
use App\Models\User;
use App\Services\MobilePushDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MobilePushDeliveryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.mobile_push.fcm.project_id', 'airmius');
    }

    public function test_fcm_delivery_is_only_marked_sent_after_provider_acceptance(): void
    {
        Http::fake([
            'https://oauth.example/token' => Http::response(['access_token' => 'oauth-token']),
            'https://fcm.googleapis.com/*' => Http::response(['name' => 'projects/airmius/messages/42']),
        ]);

        [$notification, $device] = $this->notificationAndDevice();
        $service = new class extends MobilePushDeliveryService
        {
            protected function firebaseCredentials(): array
            {
                return ['client_email' => 'push@example.test', 'private_key' => 'unused', 'project_id' => 'airmius', 'token_uri' => 'https://oauth.example/token'];
            }

            protected function firebaseAssertion(array $credentials): string
            {
                return 'signed-assertion';
            }
        };

        $service->queueForNotification($notification);
        $summary = $service->dispatchQueued();

        $this->assertSame(['processed' => 1, 'sent' => 1, 'skipped' => 0, 'failed' => 0, 'retrying' => 0], $summary);
        $delivery = MobilePushDelivery::firstOrFail();
        $this->assertSame('sent', $delivery->status);
        $this->assertSame('projects/airmius/messages/42', $delivery->provider_message_id);
        Http::assertSent(fn ($request) => $request->url() === 'https://fcm.googleapis.com/v1/projects/airmius/messages:send'
            && $request['message']['token'] === $device->token);
    }

    public function test_provider_failure_is_recorded_as_failed_instead_of_fake_sent(): void
    {
        config()->set('services.mobile_push.max_attempts', 1);
        Http::fake(['*' => Http::response(['error' => 'unavailable'], 503)]);
        [$notification] = $this->notificationAndDevice();
        $service = new class extends MobilePushDeliveryService
        {
            protected function firebaseCredentials(): array
            {
                return ['client_email' => 'push@example.test', 'private_key' => 'unused', 'project_id' => 'airmius', 'token_uri' => 'https://oauth.example/token'];
            }

            protected function firebaseAssertion(array $credentials): string
            {
                return 'signed-assertion';
            }
        };

        $service->queueForNotification($notification);
        $summary = $service->dispatchQueued();

        $this->assertSame(1, $summary['failed']);
        $this->assertSame('failed', MobilePushDelivery::firstOrFail()->status);
        $this->assertNull(MobilePushDelivery::firstOrFail()->sent_at);
    }

    public function test_transient_provider_failure_is_requeued_with_backoff(): void
    {
        config()->set('services.mobile_push.max_attempts', 5);
        config()->set('services.mobile_push.retry_base_seconds', 60);
        Http::fake(['*' => Http::response(['error' => 'temporarily unavailable'], 503)]);
        [$notification] = $this->notificationAndDevice();
        $service = new class extends MobilePushDeliveryService
        {
            protected function firebaseCredentials(): array
            {
                return ['client_email' => 'push@example.test', 'private_key' => 'unused', 'project_id' => 'airmius', 'token_uri' => 'https://oauth.example/token'];
            }
            protected function firebaseAssertion(array $credentials): string { return 'signed-assertion'; }
        };

        $service->queueForNotification($notification);
        $summary = $service->dispatchQueued();
        $delivery = MobilePushDelivery::firstOrFail();

        $this->assertSame(1, $summary['retrying']);
        $this->assertSame('queued', $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertNotNull($delivery->next_attempt_at);
        $this->assertNull($delivery->failed_at);
    }

    public function test_unregistered_fcm_token_is_failed_and_device_is_disabled(): void
    {
        Http::fake([
            'https://oauth.example/token' => Http::response(['access_token' => 'oauth-token']),
            'https://fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'UNREGISTERED']], 404),
        ]);
        [$notification, $device] = $this->notificationAndDevice();
        $service = new class extends MobilePushDeliveryService
        {
            protected function firebaseCredentials(): array
            {
                return ['client_email' => 'push@example.test', 'private_key' => 'unused', 'project_id' => 'airmius', 'token_uri' => 'https://oauth.example/token'];
            }
            protected function firebaseAssertion(array $credentials): string { return 'signed-assertion'; }
        };

        $service->queueForNotification($notification);
        $summary = $service->dispatchQueued();

        $this->assertSame(1, $summary['failed']);
        $this->assertSame('failed', MobilePushDelivery::firstOrFail()->status);
        $this->assertNotNull($device->fresh()->disabled_at);
    }

    private function notificationAndDevice(): array
    {
        $user = User::factory()->create();
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'event.reminder',
            'data' => ['title' => 'Training', 'body' => 'In 30 Minuten', 'event_id' => 42],
        ]);
        $device = MobileDeviceToken::create([
            'user_id' => $user->id,
            'device_id' => 'device-1',
            'platform' => 'android',
            'provider' => 'fcm',
            'token' => 'real-device-token',
            'token_hash' => hash('sha256', 'real-device-token'),
            'channels' => ['event_reminders'],
            'permissions' => ['notifications' => true],
        ]);

        return [$notification, $device];
    }
}
