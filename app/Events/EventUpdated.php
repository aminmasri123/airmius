<?php

namespace App\Events;

use App\Models\Event;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EventUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Event $event,
        public string $action,
    ) {
        $this->event->loadMissing('team');
    }

    public function broadcastOn(): array
    {
        $channels = [];

        if ($this->event->team_id) {
            $channels[] = new PrivateChannel('events.team.'.$this->event->team_id);
        }

        if ($this->event->resolvedClub()) {
            $channels[] = new PrivateChannel('events.club.'.$this->event->resolvedClub()->id);
        }

        if ($this->event->visibility === 'public') {
            $channels[] = new Channel('events.public');
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'event.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'event' => [
                'id' => $this->event->id,
                'club_id' => $this->event->club_id,
                'team_id' => $this->event->team_id,
                'conversation_id' => $this->event->conversation_id,
                'title' => $this->event->title,
                'type' => $this->event->type,
                'visibility' => $this->event->visibility,
                'start_time' => $this->event->start_time,
                'end_time' => $this->event->end_time,
                'location' => $this->event->location,
                'notes' => $this->event->notes,
                'recurring' => $this->event->recurring,
                'recurrence_ends_at' => $this->event->recurrence_ends_at,
                'reminder_at' => $this->event->reminder_at,
            ],
        ];
    }
}
