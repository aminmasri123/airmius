<?php

namespace App\Support;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\User;
use App\Services\MobilePushDeliveryService;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Log;
use Throwable;

class AppNotification
{
    public static function send(User|int $recipient, string $type, array $data): ?Notification
    {
        $userId = $recipient instanceof User ? $recipient->id : $recipient;

        if (!$userId) {
            return null;
        }

        $notification = Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'data' => $data,
            'read' => false,
        ]);

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
            // An unavailable push provider must never roll back or hide the
            // in-app notification. The queued delivery can be monitored and
            // retried independently.
            Log::warning('Mobile push queueing failed.', [
                'notification_id' => $notification->id,
                'user_id' => $notification->user_id,
                'type' => $notification->type,
                'message' => $exception->getMessage(),
            ]);
        }

        return $notification;
    }
}
