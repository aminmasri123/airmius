<?php

namespace App\Services;

use App\Models\MobileDeviceToken;
use App\Models\MobilePushDelivery;
use App\Models\Notification;
use App\Support\Api\V1\MobileSyncContract;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

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

        $devices = MobileDeviceToken::query()
            ->where('user_id', $notification->user_id)
            ->whereNull('disabled_at')
            ->get()
            ->filter(fn (MobileDeviceToken $device) => $this->deviceAcceptsChannel($device, $channel));

        $payload = $this->payloadFor($notification, $channel);
        $queued = 0;

        foreach ($devices as $device) {
            MobilePushDelivery::query()->create([
                'notification_id' => $notification->id,
                'mobile_device_token_id' => $device->id,
                'user_id' => $notification->user_id,
                'channel' => $channel,
                'provider' => $device->provider,
                'status' => 'queued',
                'payload' => $payload,
                'queued_at' => now(),
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
        ];

        MobilePushDelivery::query()
            ->where('status', 'queued')
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

                $delivery->update([
                    'status' => 'sent',
                    'provider_message_id' => $this->providerMessageId($delivery),
                    'sent_at' => now(),
                    'error' => null,
                ]);
                $summary['sent']++;
            });

        return $summary;
    }

    public function channelForType(string $type): ?string
    {
        return match (true) {
            Str::startsWith($type, 'event.') || Str::contains($type, 'participation') => 'event_reminders',
            Str::startsWith($type, 'chat.') || Str::contains($type, 'message') => 'chat_mentions',
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
            Str::startsWith($type, 'training.') => 'Airmius Training',
            Str::startsWith($type, 'commerce.'), Str::startsWith($type, 'marketplace.') => 'Airmius Marketplace',
            Str::startsWith($type, 'club.'), Str::startsWith($type, 'invoice.') => 'Airmius Verein',
            default => 'Airmius',
        };
    }

    protected function providerMessageId(MobilePushDelivery $delivery): string
    {
        return implode('-', [
            'airmius',
            $delivery->provider,
            $delivery->id,
            substr(hash('sha256', json_encode($delivery->payload ?? []) ?: ''), 0, 10),
        ]);
    }
}
