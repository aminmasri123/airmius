<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public User $user) {}

    public function broadcastOn(): array
    {
        return $this->audienceUserIds()
            ->map(fn (int $userId) => new PrivateChannel('users.status.'.$userId))
            ->all();
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

    private function audienceUserIds(): Collection
    {
        $conversationPeers = DB::table('conversation_users as mine')
            ->join('conversation_users as peer', 'peer.conversation_id', '=', 'mine.conversation_id')
            ->where('mine.user_id', $this->user->id)
            ->pluck('peer.user_id');

        $friends = DB::table('friendships')
            ->where('user_id', $this->user->id)
            ->pluck('friend_id')
            ->merge(
                DB::table('friendships')
                    ->where('friend_id', $this->user->id)
                    ->pluck('user_id')
            );

        return $conversationPeers
            ->merge($friends)
            ->push($this->user->id)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }
}
