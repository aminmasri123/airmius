<?php

namespace App\Support;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\User;

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

        broadcast(new NotificationCreated($notification));

        return $notification;
    }
}
