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
use Illuminate\Support\Facades\Log;

class EventService
{
    private const MAX_RECURRING_EVENTS = 370;

    public function __construct(
        private ChatService $chatService,
        private DomainEventPublisher $domainEvents,
    ) {}

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
            $createdEventIds = [];

            foreach ($events as $eventData) {
                $eventData['user_id'] ??= auth()->id();

                $event = Event::create($eventData);
                $firstEvent ??= $event;
                $createdEventIds[] = $event->id;

                $this->postEventNoticeToTeamChat($event, $teamId, $clubId, $participantIds);

                $this->broadcastSafely(fn () => broadcast(new EventUpdated($event->refresh(), 'created')));
            }

            if ($firstEvent) {
                $this->domainEvents->record(
                    count($createdEventIds) > 1 ? 'organization.event.series_created.v1' : 'organization.event.created.v1',
                    $firstEvent,
                    payload: [
                        'event_ids' => $createdEventIds,
                        'occurrence_count' => count($createdEventIds),
                        'team_id' => $teamId,
                        'club_id' => $clubId,
                        'visibility' => $firstEvent->visibility,
                        'starts_at' => $firstEvent->start_time?->toIso8601String(),
                    ],
                    audience: ['users' => $participantIds],
                );
            }

            return $firstEvent ?: $event;
        });
    }

    public function update(Event $event, array $data): bool
    {
        DB::transaction(function () use ($event, $data): void {
            $event->update($this->normalizeEventTimes($data, $data['event_timezone'] ?? null));

            $this->domainEvents->record(
                'organization.event.updated.v1',
                $event,
                payload: [
                    'changed_fields' => array_values(array_keys($event->getChanges())),
                    'team_id' => $event->team_id,
                    'club_id' => $event->club_id,
                    'starts_at' => $event->start_time?->toIso8601String(),
                ],
                audience: ['users' => $this->getParticipantIds($event->club_id, $event->team_id)],
            );
        });

        $this->broadcastSafely(fn () => broadcast(new EventUpdated($event->refresh(), 'updated')));

        return true;
    }

    public function delete(Event $event): bool
    {
        $deleted = DB::transaction(function () use ($event): bool {
            $eventId = $event->id;
            $clubId = $event->club_id;
            $teamId = $event->team_id;
            $audience = $this->getParticipantIds($clubId, $teamId);
            $deleted = $event->delete();

            if ($deleted) {
                $this->domainEvents->record(
                    'organization.event.deleted.v1',
                    Event::class,
                    $eventId,
                    payload: [
                        'team_id' => $teamId,
                        'club_id' => $clubId,
                    ],
                    audience: ['users' => $audience],
                );
            }

            return $deleted;
        });

        if ($deleted) {
            $this->broadcastSafely(fn () => broadcast(new EventUpdated($event, 'deleted')));
        }

        return $deleted;
    }

    private function broadcastSafely(callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $exception) {
            Log::warning('Event realtime broadcast failed; event change was saved.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
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

    private function teamConversationForEvent(?int $clubId, ?int $teamId, array $participantIds): ?Conversation
    {
        if (! $teamId) {
            return null;
        }

        $conversation = Conversation::firstOrCreate(
            [
                'type' => 'team',
                'team_id' => $teamId,
            ],
            [
                'club_id' => $clubId,
            ],
        );

        if (! $conversation->club_id && $clubId) {
            $conversation->update(['club_id' => $clubId]);
        }

        $participantIds = collect($participantIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $existingIds = $conversation->users()
            ->whereIn('users.id', $participantIds)
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id);

        $newParticipantIds = $participantIds->diff($existingIds)->values();

        if ($newParticipantIds->isNotEmpty()) {
            $joinedAt = now();
            $conversation->users()->syncWithoutDetaching(
                $newParticipantIds
                    ->mapWithKeys(fn ($id) => [(int) $id => ['joined_at' => $joinedAt]])
                    ->all()
            );
        }

        return $conversation;
    }

    private function postEventNoticeToTeamChat(Event $event, ?int $teamId, ?int $clubId, array $participantIds): void
    {
        $conversation = $this->teamConversationForEvent($clubId, $teamId, $participantIds);

        if (! $conversation) {
            return;
        }

        $start = $event->start_time?->timezone(config('app.timezone'))->format('d.m.Y H:i');
        $link = route('auth.events.show', $event);
        $text = trim("Trainingseinheit geplant: {$event->title}\n{$start}\n{$link}");

        $this->chatService->sendMessage(auth()->user(), $conversation->id, $text);
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

        if (empty($data['recurring']) || empty($data['recurrence_ends_at'])) {
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
        $series = match ($data['recurring']) {
            'daily' => $this->dailyOccurrences($baseStart, $until),
            'weekly', 'biweekly' => $recurrenceDays->isNotEmpty()
                ? $this->weeklyOccurrences($baseStart, $until, $recurrenceDays, $data['recurring'] === 'biweekly' ? 2 : 1)
                : [],
            'monthly' => $this->monthlyOccurrences($baseStart, $until),
            default => [],
        };

        return count($series) > 0 ? array_map(
            fn (Carbon $startTime) => $this->withoutInputTimezone(array_merge($data, [
                'start_time' => $this->toUtcDateTimeString($startTime),
                'end_time' => $duration !== null
                    ? $this->toUtcDateTimeString($startTime->copy()->addSeconds($duration))
                    : null,
                'reminder_at' => $reminderOffset !== null
                    ? $this->toUtcDateTimeString($startTime->copy()->subSeconds($reminderOffset))
                    : null,
                'conversation_id' => null,
            ])),
            array_slice($series, 0, self::MAX_RECURRING_EVENTS),
        ) : [$this->normalizeEventTimes($data, $timezone)];
    }

    private function dailyOccurrences(Carbon $baseStart, Carbon $until): array
    {
        $series = [];
        $cursor = $baseStart->copy();

        while ($cursor->lte($until) && count($series) < self::MAX_RECURRING_EVENTS) {
            $series[] = $cursor->copy();
            $cursor->addDay();
        }

        return $series;
    }

    private function weeklyOccurrences(Carbon $baseStart, Carbon $until, $recurrenceDays, int $interval): array
    {
        $series = [];
        $cursor = $baseStart->copy()->startOfDay();

        while ($cursor->lte($until) && count($series) < self::MAX_RECURRING_EVENTS) {
            if ($recurrenceDays->contains($cursor->dayOfWeek)) {
                $weeksDiff = $baseStart->copy()->startOfWeek()->diffInWeeks($cursor->copy()->startOfWeek(), false);

                if ($weeksDiff >= 0 && $weeksDiff % $interval === 0) {
                    $series[] = $cursor->copy()->setTime($baseStart->hour, $baseStart->minute, $baseStart->second);
                }
            }

            $cursor->addDay();
        }

        return $series;
    }

    private function monthlyOccurrences(Carbon $baseStart, Carbon $until): array
    {
        $series = [];
        $months = 0;

        do {
            $startTime = $baseStart->copy()->addMonthsNoOverflow($months);

            if ($startTime->lte($until)) {
                $series[] = $startTime;
            }

            $months++;
        } while ($startTime->lte($until) && count($series) < self::MAX_RECURRING_EVENTS);

        return $series;
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
