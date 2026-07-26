<?php

namespace App\Services;

use Firebase\JWT\JWT;
use App\Models\MobileDeviceToken;
use App\Models\MobilePushDelivery;
use App\Models\Notification;
use App\Models\User;
use App\Support\Api\V1\MobileSyncContract;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

class MobilePushDeliveryService
{
    public const CONTRACT_VERSION = '2026-06-03';

    public function queueForNotification(Notification $notification): array
    {
        $channel = $this->channelForType($notification->type);

        if ($channel === null) {
            return [
                'queued' => 0,
                'channel' => null,
                'reason' => 'unsupported_notification_type',
            ];
        }

        $user = User::query()->find($notification->user_id);
        if ($user && ! $this->userAcceptsNotification($user, $notification, $channel)) {
            return [
                'queued' => 0,
                'channel' => $channel,
                'reason' => 'user_channel_disabled',
            ];
        }

        $devices = MobileDeviceToken::query()
            ->where('user_id', $notification->user_id)
            ->whereNull('disabled_at')
            ->get()
            ->filter(fn (MobileDeviceToken $device) => $this->deviceAcceptsChannel($device, $channel));

        $payload = $this->payloadFor($notification, $channel);
        $queued = 0;

        foreach ($devices as $device) {
            $nextAttemptAt = $this->nextAllowedAttemptAt($user, $device, $channel);
            MobilePushDelivery::query()->create([
                'notification_id' => $notification->id,
                'mobile_device_token_id' => $device->id,
                'user_id' => $notification->user_id,
                'channel' => $channel,
                'provider' => $device->provider,
                'status' => 'queued',
                'payload' => $payload,
                'queued_at' => now(),
                'next_attempt_at' => $nextAttemptAt,
            ]);

            $queued++;
        }

        return [
            'queued' => $queued,
            'channel' => $channel,
            'reason' => $queued > 0 ? 'queued' : 'no_active_matching_devices',
        ];
    }

    public function dispatchQueued(int $limit = 100): array
    {
        $summary = [
            'processed' => 0,
            'sent' => 0,
            'skipped' => 0,
            'failed' => 0,
            'retrying' => 0,
        ];

        MobilePushDelivery::query()
            ->where('status', 'queued')
            ->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->oldest('queued_at')
            ->limit($limit)
            ->with('device')
            ->get()
            ->each(function (MobilePushDelivery $delivery) use (&$summary) {
                $summary['processed']++;

                if (! $delivery->device || $delivery->device->disabled_at) {
                    $delivery->update([
                        'status' => 'skipped',
                        'error' => 'device_disabled_or_missing',
                        'failed_at' => now(),
                    ]);
                    $summary['skipped']++;

                    return;
                }

                if (! filled($delivery->device->token)) {
                    $delivery->update([
                        'status' => 'failed',
                        'error' => 'device_token_missing',
                        'failed_at' => now(),
                    ]);
                    $summary['failed']++;

                    return;
                }

                try {
                    $delivery->increment('attempts');
                    $delivery->forceFill(['last_attempt_at' => now(), 'next_attempt_at' => null])->save();
                    $messageId = $this->sendToProvider($delivery);
                    $delivery->update([
                        'status' => 'sent',
                        'provider_message_id' => $messageId,
                        'sent_at' => now(),
                        'failed_at' => null,
                        'error' => null,
                    ]);
                    $summary['sent']++;
                } catch (\Throwable $error) {
                    if ($this->isPermanentDeviceError($error)) {
                        $delivery->device->update(['disabled_at' => now()]);
                    }

                    $maxAttempts = max(1, (int) config('services.mobile_push.max_attempts', 5));
                    $willRetry = ! $this->isPermanentDeviceError($error) && $delivery->attempts < $maxAttempts;
                    $delivery->update([
                        'status' => $willRetry ? 'queued' : 'failed',
                        'error' => Str::limit($error->getMessage(), 1000, ''),
                        'next_attempt_at' => $willRetry ? now()->addSeconds($this->retryDelay($delivery->attempts)) : null,
                        'failed_at' => $willRetry ? null : now(),
                    ]);
                    $summary[$willRetry ? 'retrying' : 'failed']++;
                }
            });

        return $summary;
    }

    public function channelForType(string $type): ?string
    {
        return match (true) {
            Str::startsWith($type, 'event.') || Str::contains($type, 'participation') => 'event_reminders',
            Str::startsWith($type, 'chat.') || Str::contains($type, 'message') => 'chat_mentions',
            Str::startsWith($type, 'friend.') || Str::startsWith($type, 'social.') => 'social_updates',
            Str::startsWith($type, 'training.') => 'training_updates',
            Str::startsWith($type, 'commerce.') || Str::startsWith($type, 'marketplace.') || Str::startsWith($type, 'outfit.') => 'commerce_orders',
            Str::startsWith($type, 'club.') || Str::startsWith($type, 'invoice.') || Str::contains($type, 'billing') => 'club_billing',
            default => null,
        };
    }

    protected function deviceAcceptsChannel(MobileDeviceToken $device, string $channel): bool
    {
        $permissions = $device->permissions ?? [];

        if (array_key_exists('notifications', $permissions) && $permissions['notifications'] === false) {
            return false;
        }

        $channels = array_filter($device->channels ?? []);

        return $channels === [] || in_array($channel, $channels, true);
    }

    protected function userAcceptsNotification(User $user, Notification $notification, string $channel): bool
    {
        $preferences = $user->notification_channels;
        if (! is_array($preferences)) {
            return true;
        }

        $key = match (true) {
            Str::startsWith($notification->type, 'chat.') || Str::contains($notification->type, 'message') => 'chat',
            Str::startsWith($notification->type, 'invoice.') || Str::contains($notification->type, 'billing') => 'billing',
            Str::startsWith($notification->type, 'club.') => 'club',
            Str::startsWith($notification->type, 'commerce.') || Str::startsWith($notification->type, 'marketplace.') || Str::startsWith($notification->type, 'outfit.') => 'billing',
            Str::startsWith($notification->type, 'friend.') || Str::startsWith($notification->type, 'social.') => 'club',
            Str::startsWith($notification->type, 'event.') || Str::startsWith($notification->type, 'training.') => 'club',
            default => null,
        };

        return $key === null || ! array_key_exists($key, $preferences) || (bool) $preferences[$key];
    }

    protected function nextAllowedAttemptAt(?User $user, MobileDeviceToken $device, string $channel): ?\Carbon\Carbon
    {
        if (! $user || in_array($channel, ['event_reminders', 'chat_mentions'], true)) {
            return null;
        }

        $quietTime = (string) ($user->notification_quiet_time ?? '');
        if ($quietTime === '' || $quietTime === 'none') {
            return null;
        }

        try {
            $timezone = new \DateTimeZone($device->timezone ?: config('app.timezone', 'UTC'));
        } catch (\Throwable) {
            $timezone = new \DateTimeZone(config('app.timezone', 'UTC'));
        }

        $localNow = now($timezone);
        if ($quietTime === 'weekend') {
            if ($localNow->dayOfWeek < 6) {
                return null;
            }

            return $localNow->copy()->next(\Carbon\Carbon::MONDAY)->setTime(8, 0)->setTimezone(config('app.timezone', 'UTC'));
        }

        $startHour = $quietTime === 'early' ? 20 : 22;
        $endHour = $quietTime === 'early' ? 8 : 7;
        $isQuiet = $localNow->hour >= $startHour || $localNow->hour < $endHour;
        if (! $isQuiet) {
            return null;
        }

        $end = $localNow->copy()->setTime($endHour, 0);
        if ($localNow->hour >= $startHour) {
            $end->addDay();
        }

        return $end->setTimezone(config('app.timezone', 'UTC'));
    }

    protected function payloadFor(Notification $notification, string $channel): array
    {
        $data = $notification->data ?? [];
        $channelContract = collect(MobileSyncContract::pushChannels())
            ->firstWhere('key', $channel) ?? [];

        return [
            'contract_version' => self::CONTRACT_VERSION,
            'notification_id' => $notification->id,
            'type' => $notification->type,
            'channel' => $channel,
            'title' => (string) ($data['title'] ?? $this->titleForType($notification->type)),
            'body' => (string) ($data['body'] ?? $data['message'] ?? $data['description'] ?? ''),
            'deep_link' => $this->replacePlaceholders((string) Arr::get($channelContract, 'deep_link', 'airmius://dashboard'), $data),
            'fallback_url' => $this->replacePlaceholders((string) Arr::get($channelContract, 'fallback_url', '/dashboard'), $data),
            'importance' => Arr::get($channelContract, 'importance', 'default'),
            'data' => Arr::except($data, ['token', 'password']),
        ];
    }

    protected function replacePlaceholders(string $template, array $data): string
    {
        return preg_replace_callback('/\{([A-Za-z0-9_]+)\}/', function (array $matches) use ($data) {
            $key = $matches[1];
            $snake = Str::snake($key);

            return (string) ($data[$key] ?? $data[$snake] ?? $matches[0]);
        }, $template) ?? $template;
    }

    protected function titleForType(string $type): string
    {
        return match (true) {
            Str::startsWith($type, 'event.') => 'Airmius Event',
            Str::startsWith($type, 'chat.') => 'Airmius Chat',
            Str::startsWith($type, 'friend.'), Str::startsWith($type, 'social.') => 'Airmius Netzwerk',
            Str::startsWith($type, 'training.') => 'Airmius Training',
            Str::startsWith($type, 'commerce.'), Str::startsWith($type, 'marketplace.') => 'Airmius Marketplace',
            Str::startsWith($type, 'club.'), Str::startsWith($type, 'invoice.') => 'Airmius Verein',
            default => 'Airmius',
        };
    }

    protected function sendToProvider(MobilePushDelivery $delivery): string
    {
        return match ($delivery->provider) {
            'fcm', 'apns' => $this->sendWithFcm($delivery),
            'expo' => $this->sendWithExpo($delivery),
            default => throw new RuntimeException("Unsupported push provider: {$delivery->provider}"),
        };
    }

    protected function sendWithFcm(MobilePushDelivery $delivery): string
    {
        $credentials = $this->firebaseCredentials();
        $projectId = (string) (config('services.mobile_push.fcm.project_id') ?: ($credentials['project_id'] ?? ''));

        if ($projectId === '') {
            throw new RuntimeException('FIREBASE_PROJECT_ID is not configured.');
        }

        $tokenResponse = Http::asForm()->timeout(10)->post(
            (string) ($credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token'),
            [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->firebaseAssertion($credentials),
            ],
        )->throw()->json();

        $accessToken = (string) Arr::get($tokenResponse, 'access_token', '');
        if ($accessToken === '') {
            throw new RuntimeException('Firebase OAuth response did not contain an access token.');
        }

        $payload = $delivery->payload ?? [];
        $data = collect($payload)->mapWithKeys(function ($value, $key) {
            return [(string) $key => is_scalar($value) || $value === null
                ? (string) $value
                : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)];
        })->all();

        $response = Http::withToken($accessToken)->acceptJson()->timeout(15)->post(
            "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send",
            [
                'message' => [
                    'token' => $delivery->device->token,
                    'notification' => [
                        'title' => (string) ($payload['title'] ?? 'Airmius'),
                        'body' => (string) ($payload['body'] ?? ''),
                    ],
                    'data' => $data,
                    'android' => ['priority' => ($payload['importance'] ?? 'default') === 'high' ? 'high' : 'normal'],
                    'apns' => ['payload' => ['aps' => ['sound' => 'default']]],
                ],
            ],
        )->throw()->json();

        $messageId = (string) Arr::get($response, 'name', '');
        if ($messageId === '') {
            throw new RuntimeException('Firebase response did not contain a message ID.');
        }

        return $messageId;
    }

    protected function sendWithExpo(MobilePushDelivery $delivery): string
    {
        $payload = $delivery->payload ?? [];
        $request = Http::acceptJson()->timeout(15);
        if (filled(config('services.mobile_push.expo.access_token'))) {
            $request = $request->withToken((string) config('services.mobile_push.expo.access_token'));
        }

        $response = $request->post('https://exp.host/--/api/v2/push/send', [
            'to' => $delivery->device->token,
            'title' => (string) ($payload['title'] ?? 'Airmius'),
            'body' => (string) ($payload['body'] ?? ''),
            'data' => $payload,
            'sound' => 'default',
        ])->throw()->json();

        $status = (string) Arr::get($response, 'data.status', '');
        if ($status !== 'ok') {
            throw new RuntimeException((string) Arr::get($response, 'data.message', 'Expo rejected the push notification.'));
        }

        return (string) Arr::get($response, 'data.id', 'expo-accepted');
    }

    protected function firebaseCredentials(): array
    {
        $path = (string) config('services.mobile_push.fcm.credentials');
        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('FIREBASE_CREDENTIALS must point to a readable service-account JSON file.');
        }

        $credentials = json_decode((string) file_get_contents($path), true);
        if (! is_array($credentials) || blank($credentials['client_email'] ?? null) || blank($credentials['private_key'] ?? null)) {
            throw new RuntimeException('Firebase service-account JSON is invalid.');
        }

        return $credentials;
    }

    protected function firebaseAssertion(array $credentials): string
    {
        $now = time();

        return JWT::encode([
            'iss' => $credentials['client_email'],
            'sub' => $credentials['client_email'],
            'aud' => $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        ], $credentials['private_key'], 'RS256');
    }

    protected function retryDelay(int $attempt): int
    {
        $base = max(10, (int) config('services.mobile_push.retry_base_seconds', 60));
        return min(3600, $base * (2 ** max(0, $attempt - 1)));
    }

    protected function isPermanentDeviceError(\Throwable $error): bool
    {
        $text = Str::upper($error->getMessage());
        if ($error instanceof RequestException) {
            $text .= ' '.Str::upper($error->response->body());
        }

        return Str::contains($text, [
            'UNREGISTERED',
            'SENDER_ID_MISMATCH',
            'DEVICE_NOT_REGISTERED',
            'REGISTRATION-TOKEN-NOT-REGISTERED',
            'BADDEVICETOKEN',
            'UNREGISTERED DEVICE',
        ]);
    }
}
