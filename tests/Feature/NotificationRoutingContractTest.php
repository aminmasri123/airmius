<?php

namespace Tests\Feature;

use App\Events\NotificationCreated;
use App\Models\MobileDeviceToken;
use App\Models\MobilePushDelivery;
use App\Models\User;
use App\Support\AppNotification;
use App\Support\NotificationRouting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationRoutingContractTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_notification_settings_catalogs_have_key_and_placeholder_parity(): void
    {
        $reference = Arr::dot(require lang_path('de/notification_settings.php'));

        foreach (['de', 'en', 'fr', 'ar'] as $locale) {
            $catalog = Arr::dot(require lang_path($locale.'/notification_settings.php'));
            $this->assertSame(array_keys($reference), array_keys($catalog), $locale.' key parity');

            foreach ($reference as $key => $source) {
                $this->assertSame(
                    $this->placeholders((string) $source),
                    $this->placeholders((string) $catalog[$key]),
                    $locale.' placeholder parity for '.$key,
                );
            }
        }
    }

    public function test_topic_preferences_suppress_noise_but_never_critical_security_alerts(): void
    {
        Event::fake([NotificationCreated::class]);
        $user = User::factory()->create([
            'notification_channels' => [
                'chat' => false,
                'club' => false,
                'billing' => false,
                'marketing' => false,
            ],
        ]);

        $this->assertNull(AppNotification::send($user, 'chat.message', ['title' => 'Muted']));
        $this->assertDatabaseCount('notifications', 0);

        $critical = AppNotification::send($user, 'security.suspicious_login', [
            'title' => 'Security alert',
        ]);

        $this->assertNotNull($critical);
        $this->assertSame('security', $critical->category);
        $this->assertSame('critical', $critical->priority);
        $this->assertSame('critical', data_get($critical->data, 'routing.priority'));
    }

    public function test_entity_notifications_receive_native_and_web_action_urls(): void
    {
        $cases = [
            ['training.plan.changed', ['training_plan_id' => 41, 'url' => '/training'], '/training?plan=41', 'airmius://training/plans/41'],
            ['training.log.saved', ['training_log_id' => 42], '/training/logs/42', 'airmius://training/logs/42'],
            ['event.reminder', ['event_id' => 43], '/events/43', 'airmius://events/43'],
            ['chat.message', ['conversation_id' => 44], '/chat?conversation=44', 'airmius://chat/44'],
            ['team.profile_updated', ['team_id' => 45], '/teams/45', 'airmius://teams/45'],
            ['club.announcement', ['club_id' => 46], '/clubs/46', 'airmius://clubs/46'],
        ];

        foreach ($cases as [$type, $input, $webUrl, $mobileUrl]) {
            $data = NotificationRouting::normalizeActionData($type, $input);

            $this->assertSame($webUrl, $data['action_url'], $type.' web URL');
            $this->assertSame($mobileUrl, $data['mobile_url'], $type.' mobile URL');
            $this->assertSame($mobileUrl, $data['deep_link'], $type.' deep link');
        }
    }

    public function test_unsupported_web_notification_links_use_native_notification_center_fallback(): void
    {
        $data = NotificationRouting::normalizeActionData('learning.drip.unlocked', [
            'url' => '/learning/courses/9',
        ]);

        $this->assertSame('/learning/courses/9', $data['url']);
        $this->assertSame('airmius://notifications', $data['mobile_url']);
        $this->assertSame('airmius://notifications', $data['deep_link']);
    }

    public function test_ai_provider_alerts_open_native_admin_settings(): void
    {
        foreach (['admin.ai_token.problem', 'admin.ai_token.expiring'] as $type) {
            $data = NotificationRouting::normalizeActionData($type, [
                'url' => '/admin/settings',
            ]);

            $this->assertSame('/admin/settings', $data['action_url']);
            $this->assertSame('airmius://admin/settings', $data['mobile_url']);
            $this->assertSame('airmius://admin/settings', $data['deep_link']);
        }
    }

    public function test_dedupe_key_is_atomic_and_queues_only_one_push(): void
    {
        Event::fake([NotificationCreated::class]);
        $user = User::factory()->create([
            'notification_channels' => ['push' => true, 'club' => true],
            'notification_quiet_time' => 'none',
        ]);
        $this->deviceFor($user, 'dedupe-device', ['event_reminders']);

        $first = AppNotification::send(
            $user,
            'event.reminder',
            ['title' => 'Training', 'event_id' => 42],
            ['dedupe_key' => 'event:42:reminder:30m'],
        );
        $second = AppNotification::send(
            $user,
            'event.reminder',
            ['title' => 'Training retry', 'event_id' => 42],
            ['dedupe_key' => 'event:42:reminder:30m'],
        );

        $this->assertSame($first?->id, $second?->id);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('mobile_push_deliveries', 1);
    }

    public function test_push_opt_out_and_quiet_hours_are_enforced_while_critical_alerts_are_immediate(): void
    {
        Event::fake([NotificationCreated::class]);
        Carbon::setTestNow(Carbon::parse('2026-08-08 23:30:00', 'Europe/Berlin'));

        $optedOut = User::factory()->create([
            'notification_channels' => ['push' => false, 'club' => true],
        ]);
        $this->deviceFor($optedOut, 'opted-out', ['event_reminders']);
        $notification = AppNotification::send($optedOut, 'event.reminder', ['title' => 'Reminder']);

        $this->assertNotNull($notification);
        $this->assertDatabaseCount('mobile_push_deliveries', 0);

        $quietUser = User::factory()->create([
            'notification_channels' => ['push' => true, 'club' => true],
            'notification_quiet_time' => 'late',
        ]);
        $this->deviceFor($quietUser, 'quiet-device', ['event_reminders', 'social_updates']);

        AppNotification::send($quietUser, 'event.reminder', ['title' => 'Later']);
        $delayed = MobilePushDelivery::query()->where('user_id', $quietUser->id)->latest('id')->firstOrFail();
        $this->assertNotNull($delayed->next_attempt_at);
        $this->assertSame('2026-08-09 07:00', $delayed->next_attempt_at->timezone('Europe/Berlin')->format('Y-m-d H:i'));

        AppNotification::send($quietUser, 'security.suspicious_login', ['title' => 'Now']);
        $critical = MobilePushDelivery::query()->where('user_id', $quietUser->id)->latest('id')->firstOrFail();
        $this->assertNull($critical->next_attempt_at);
        $this->assertSame('critical', $critical->payload['priority']);
    }

    public function test_settings_api_patch_preserves_unrelated_privacy_and_event_preferences(): void
    {
        $user = User::factory()->create([
            'event_radius_km' => 75,
            'event_default_sport_ids' => [3, 4],
            'ads_personalization_consent' => true,
            'ads_measurement_consent' => true,
        ]);
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/settings', [
            'notification_channels' => ['push' => false, 'chat' => false],
            'notification_quiet_time' => 'early',
        ])->assertOk();

        $fresh = $user->fresh();
        $this->assertSame(75, $fresh->event_radius_km);
        $this->assertSame([3, 4], $fresh->event_default_sport_ids);
        $this->assertTrue($fresh->ads_personalization_consent);
        $this->assertTrue($fresh->ads_measurement_consent);
    }

    public function test_web_settings_expose_and_save_the_same_localized_notification_contract(): void
    {
        $user = User::factory()->create([
            'language' => 'fr',
            'country' => 'FR',
            'notification_channels' => ['push' => true, 'chat' => false],
            'notification_quiet_time' => 'early',
        ]);

        $this->actingAs($user)
            ->get(route('auth.settings'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('notificationPreferences.channels.push', true)
                ->where('notificationPreferences.channels.chat', false)
                ->where('notificationPreferences.quiet_time', 'early'));

        $this->actingAs($user)
            ->put(route('auth.settings.update'), [
                'country' => 'FR',
                'notification_channels' => [
                    'push' => false,
                    'email' => true,
                    'chat' => true,
                    'club' => false,
                    'billing' => true,
                    'marketing' => false,
                ],
                'notification_quiet_time' => 'weekend',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', __('notification_settings.responses.settings_saved', locale: 'fr'));

        $fresh = $user->fresh();
        $this->assertFalse($fresh->notification_channels['push']);
        $this->assertTrue($fresh->notification_channels['chat']);
        $this->assertSame('weekend', $fresh->notification_quiet_time);
    }

    private function deviceFor(User $user, string $deviceId, array $channels): MobileDeviceToken
    {
        return MobileDeviceToken::query()->create([
            'user_id' => $user->id,
            'device_id' => $deviceId,
            'platform' => 'android',
            'provider' => 'fcm',
            'token' => $deviceId.'-token',
            'token_hash' => hash('sha256', $deviceId.'-token'),
            'channels' => $channels,
            'permissions' => ['notifications' => true],
            'timezone' => 'Europe/Berlin',
        ]);
    }

    /** @return array<int, string> */
    private function placeholders(string $value): array
    {
        preg_match_all('/:[a-zA-Z_][a-zA-Z0-9_]*/', $value, $matches);
        $placeholders = $matches[0];
        sort($placeholders);

        return $placeholders;
    }
}
