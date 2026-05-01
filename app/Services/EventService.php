<?php

namespace App\Services;

use App\Events\EventUpdated;
use App\Models\Club;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\Team;
use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

class EventService
{
    public function create(array $data): Event
    {
        return DB::transaction(function () use ($data) {
            $teamId = $data['team_id'] ?? null;
            $clubId = $data['club_id'] ?? null;

            if ($teamId && ! $clubId) {
                $team = Team::find($teamId);
                $clubId = $team?->club_id;
            }

            $participantIds = $this->getParticipantIds($clubId, $teamId);
            $events = $this->expandRecurringEvents($data);
            $event = null;
            $firstEvent = null;

            foreach ($events as $eventData) {
                $conversation = $this->createConversationForEvent($clubId, $teamId, $participantIds);
                $eventData['conversation_id'] = $conversation->id;

                $event = Event::create($eventData);
                $firstEvent ??= $event;

                broadcast(new EventUpdated($event->refresh(), 'created'));
            }

            return $firstEvent ?: $event;
        });
    }

    public function update(Event $event, array $data): bool
    {
        $event->update($this->normalizeEventTimes($data, $data['event_timezone'] ?? null));

        broadcast(new EventUpdated($event->refresh(), 'updated'));

        return true;
    }

    public function delete(Event $event): bool
    {
        broadcast(new EventUpdated($event, 'deleted'));

        return $event->delete();
    }

    private function getParticipantIds(?int $clubId, ?int $teamId): array
    {
        $participantIds = collect([auth()->id()]);

        if ($teamId) {
            $participantIds = $participantIds->merge(
                Team::find($teamId)?->users()->pluck('users.id') ?? []
            );
        } elseif ($clubId) {
            $club = Club::find($clubId);

            if ($club) {
                $participantIds = $participantIds->merge($club->users()->pluck('users.id'));
            }
        }

        return $participantIds->filter()->unique()->values()->all();
    }

    private function createConversationForEvent(?int $clubId, ?int $teamId, array $participantIds): Conversation
    {
        $conversation = Conversation::create([
            'type' => 'event',
            'club_id' => $clubId,
            'team_id' => $teamId,
        ]);

        $conversation->users()->sync($participantIds);

        return $conversation;
    }

    private function expandRecurringEvents(array $data): array
    {
        $timezone = $this->resolveTimezone($data['event_timezone'] ?? null);
        $recurrenceDays = collect($data['recurrence_days'] ?? [])
            ->map(fn ($day) => (int) $day)
            ->unique()
            ->values();

        $data['recurrence_days'] = $recurrenceDays->isNotEmpty()
            ? $recurrenceDays->all()
            : null;

        if (
            ! in_array($data['recurring'] ?? '', ['weekly', 'biweekly'], true)
            || $recurrenceDays->isEmpty()
            || empty($data['recurrence_ends_at'])
        ) {
            return [$this->normalizeEventTimes($data, $timezone)];
        }

        $baseStart = Carbon::parse($data['start_time'], $timezone);
        $until = Carbon::parse($data['recurrence_ends_at'], $timezone)->endOfDay();
        $duration = ! empty($data['end_time'])
            ? $baseStart->diffInSeconds(Carbon::parse($data['end_time'], $timezone), false)
            : null;
        $reminderOffset = ! empty($data['reminder_at'])
            ? Carbon::parse($data['reminder_at'], $timezone)->diffInSeconds($baseStart, false)
            : null;
        $interval = $data['recurring'] === 'biweekly' ? 2 : 1;
        $series = [];
        $cursor = $baseStart->copy()->startOfDay();

        while ($cursor->lte($until)) {
            if ($recurrenceDays->contains($cursor->dayOfWeek)) {
                $weeksDiff = $baseStart->copy()->startOfWeek()->diffInWeeks($cursor->copy()->startOfWeek(), false);

                if ($weeksDiff >= 0 && $weeksDiff % $interval === 0) {
                    $startTime = $cursor->copy()->setTime($baseStart->hour, $baseStart->minute, $baseStart->second);

                    $series[] = array_merge($data, [
                        'start_time' => $this->toUtcDateTimeString($startTime),
                        'end_time' => $duration !== null
                            ? $this->toUtcDateTimeString($startTime->copy()->addSeconds($duration))
                            : null,
                        'reminder_at' => $reminderOffset !== null
                            ? $this->toUtcDateTimeString($startTime->copy()->subSeconds($reminderOffset))
                            : null,
                        'conversation_id' => null,
                    ]);
                }
            }

            $cursor->addDay();
        }

        return count($series) > 0 ? array_map(
            fn (array $eventData) => $this->withoutInputTimezone($eventData),
            $series,
        ) : [$this->normalizeEventTimes($data, $timezone)];
    }

    private function normalizeEventTimes(array $data, DateTimeZone|string|null $timezone = null): array
    {
        $timezone = $this->resolveTimezone($timezone);

        foreach (['start_time', 'end_time', 'reminder_at'] as $field) {
            if (! empty($data[$field])) {
                $data[$field] = $this->toUtcDateTimeString(Carbon::parse($data[$field], $timezone));
            }
        }

        return $this->withoutInputTimezone($data);
    }

    private function withoutInputTimezone(array $data): array
    {
        unset($data['event_timezone']);

        return $data;
    }

    private function resolveTimezone(DateTimeZone|string|null $timezone): DateTimeZone
    {
        if ($timezone instanceof DateTimeZone) {
            return $timezone;
        }

        return new DateTimeZone($timezone ?: 'UTC');
    }

    private function toUtcDateTimeString(Carbon $date): string
    {
        return $date->copy()->setTimezone('UTC')->toDateTimeString();
    }
}
