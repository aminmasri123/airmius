<?php

namespace App\Services;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\User;
use App\Support\NotificationRouting;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

final class NotificationRouter
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array{category?: string, priority?: string, dedupe_key?: string, bypass_preferences?: bool}  $options
     */
    public function send(User $recipient, string $type, array $data, array $options = []): ?Notification
    {
        $data = NotificationRouting::normalizeActionData($type, $data);
        $category = NotificationRouting::normalizeCategory(
            Arr::get($options, 'category', Arr::get($data, 'routing.category')),
            $type,
        );
        $priority = NotificationRouting::normalizePriority(
            Arr::get($options, 'priority', Arr::get($data, 'routing.priority')),
            $type,
        );

        if (! Arr::get($options, 'bypass_preferences', false)
            && ! NotificationRouting::topicEnabled($recipient, $type, $category, $priority)) {
            return null;
        }

        $data['routing'] = array_merge(
            is_array($data['routing'] ?? null) ? $data['routing'] : [],
            ['category' => $category, 'priority' => $priority],
        );

        $dedupeKey = $this->dedupeKey($recipient, $type, Arr::get($options, 'dedupe_key', $data['dedupe_key'] ?? null));
        $values = [
            'type' => $type,
            'category' => $category,
            'priority' => $priority,
            'data' => $data,
            'read' => false,
        ];

        if ($dedupeKey === null) {
            $notification = $recipient->appNotifications()->create($values);
        } else {
            $notification = $recipient->appNotifications()->firstOrCreate(
                ['dedupe_key' => $dedupeKey],
                $values,
            );

            if (! $notification->wasRecentlyCreated) {
                return $notification;
            }
        }

        $this->deliver($notification);

        return $notification;
    }

    private function dedupeKey(User $recipient, string $type, mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return hash('sha256', $recipient->getKey().'|'.$type.'|'.$value);
    }

    private function deliver(Notification $notification): void
    {
        try {
            broadcast(new NotificationCreated($notification));
        } catch (BroadcastException $exception) {
            Log::warning('Notification broadcast failed.', [
                'notification_id' => $notification->id,
                'user_id' => $notification->user_id,
                'type' => $notification->type,
                'message' => $exception->getMessage(),
            ]);
        }

        try {
            app(MobilePushDeliveryService::class)->queueForNotification($notification);
        } catch (Throwable $exception) {
            Log::warning('Mobile push queueing failed.', [
                'notification_id' => $notification->id,
                'user_id' => $notification->user_id,
                'type' => $notification->type,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
