<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public User $user) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('users.status'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'user.status.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'status' => $this->user->status,
            ],
        ];
    }
}
