<?php

namespace App\Services;

use App\Events\EventUpdated;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

class EventService
{
    public function create(array $data): Event
    {
        return DB::transaction(function () use ($data) {
            $event = Event::create($data);

            $conversation = $this->createEventConversation($event);

            $event->forceFill([
                'conversation_id' => $conversation->id,
            ])->save();

            broadcast(new EventUpdated($event->refresh(), 'created'));

            return $event;
        });
    }

    public function update(Event $event, array $data): bool
    {
        $event->update($data);

        broadcast(new EventUpdated($event->refresh(), 'updated'));

        return true;
    }

    public function delete(Event $event): bool
    {
        broadcast(new EventUpdated($event, 'deleted'));

        return $event->delete();
    }

    private function createEventConversation(Event $event): Conversation
    {
        $club = $event->resolvedClub();
        $conversation = Conversation::create([
            'type' => 'event',
            'club_id' => $club?->id,
            'team_id' => $event->team_id,
        ]);

        $participantIds = collect([auth()->id()]);

        if ($event->team_id) {
            $participantIds = $participantIds->merge(
                Team::find($event->team_id)?->users()->pluck('users.id') ?? []
            );
        } elseif ($club) {
            $participantIds = $participantIds->merge($club->users()->pluck('users.id'));
        }

        $conversation->users()->sync($participantIds->filter()->unique()->values());

        return $conversation;
    }
}
