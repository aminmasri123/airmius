<?php

namespace App\Events;

use App\Models\Notification;
use App\Support\NotificationRouting;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Notification $notification) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('notifications.user.'.$this->notification->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        $data = NotificationRouting::normalizeActionData(
            $this->notification->type,
            $this->notification->data ?: [],
        );

        return [
            'notification' => [
                'id' => $this->notification->id,
                'type' => $this->notification->type,
                'category' => $this->notification->category,
                'priority' => $this->notification->priority,
                'data' => $data,
                'read' => $this->notification->read,
                'created_at' => $this->notification->created_at,
            ],
        ];
    }
}
