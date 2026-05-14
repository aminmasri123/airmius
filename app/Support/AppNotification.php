<?php

namespace App\Support;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Log;

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

        return $notification;
    }
}
