<?php

namespace App\Services;

use App\Events\EventUpdated;
use App\Models\Club;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\EventRecurrenceException;
use App\Models\EventRecurrenceRuleVersion;
use App\Models\EventRecurrenceSeries;
use App\Models\Team;
use App\Models\User;
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
        private EventNotificationService $notifications,
    ) {}

    public function create(array $data): Event
    {
        $event = DB::transaction(function () use ($data) {
            $teamId = $data['team_id'] ?? null;
            $clubId = $data['club_id'] ?? null;

            if ($teamId && ! $clubId) {
                $team = Team::find($teamId);
                $clubId = $team?->club_id;
            }

            $participantIds = $this->getParticipantIds($clubId, $teamId);
            [$events, $series, $ruleVersion] = $this->expandRecurringEvents($data);
            $event = null;
            $firstEvent = null;
            $createdEventIds = [];

            foreach ($events as $eventData) {
                $eventData['user_id'] ??= auth()->id();
                if ($series && $ruleVersion) {
                    $eventData['recurrence_series_id'] = $series->id;
                    $eventData['recurrence_rule_version_id'] = $ruleVersion->id;
                }

                $event = Event::unguarded(fn () => Event::create($eventData));
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
                        'sport_route_id' => $firstEvent->sport_route_id,
                        'starts_at' => $firstEvent->start_time?->toIso8601String(),
                    ],
                    audience: ['users' => $participantIds],
                );
            }

            return $firstEvent ?: $event;
        });

        $actor = auth()->user();
        if ($actor instanceof User) {
            $this->notifications->notifyPublished($event, $actor);
        }

        return $event;
    }

    public function update(Event $event, array $data): bool
    {
        DB::transaction(function () use ($event, $data): void {
            if (($data['update_scope'] ?? null) === 'future_series' && $event->recurrence_series_id) {
                $this->updateFutureSeries($event, $data);

                return;
            }

            $event->update($this->normalizeEventTimes($data, $data['event_timezone'] ?? null));

            $this->domainEvents->record(
                'organization.event.updated.v1',
                $event,
                payload: [
                    'changed_fields' => array_values(array_keys($event->getChanges())),
                    'team_id' => $event->team_id,
                    'club_id' => $event->club_id,
                    'starts_at' => $event->start_time?->toIso8601String(),
                    'sport_route_id' => $event->sport_route_id,
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
            return [[$this->normalizeEventTimes($data, $timezone)], null, null];
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

        if (count($series) === 0) {
            return [[$this->normalizeEventTimes($data, $timezone)], null, null];
        }

        [$seriesModel, $ruleVersion] = $this->persistSeriesRule($data, $timezone, $baseStart, $until);
        $exceptions = $this->normalizedExceptions($data['recurrence_exceptions'] ?? [], $timezone, $ruleVersion);

        $events = array_values(array_filter(array_map(
            fn (Carbon $startTime) => $this->eventDataForOccurrence(
                $data,
                $startTime,
                $duration,
                $reminderOffset,
                $timezone,
                $ruleVersion,
                $exceptions,
            ),
            array_slice($series, 0, self::MAX_RECURRING_EVENTS),
        )));

        return [$events, $seriesModel, $ruleVersion];
    }

    private function eventDataForOccurrence(
        array $data,
        Carbon $startTime,
        ?int $duration,
        ?int $reminderOffset,
        DateTimeZone $timezone,
        EventRecurrenceRuleVersion $ruleVersion,
        array $exceptions,
    ): ?array {
        $localDate = $startTime->copy()->timezone($timezone)->toDateString();
        $exception = $exceptions[$localDate] ?? null;

        if (($exception['kind'] ?? null) === 'cancelled') {
            return null;
        }

        $effectiveStart = $startTime->copy();
        if (! empty($exception['starts_at'])) {
            $effectiveStart = Carbon::parse($exception['starts_at'], $timezone);
        }

        return $this->withoutInputTimezone(array_merge($data, [
            'start_time' => $this->toUtcDateTimeString($effectiveStart),
            'end_time' => $duration !== null ? $this->toUtcDateTimeString($effectiveStart->copy()->addSeconds($duration)) : null,
            'reminder_at' => $reminderOffset !== null ? $this->toUtcDateTimeString($effectiveStart->copy()->subSeconds($reminderOffset)) : null,
            'conversation_id' => null,
            'recurrence_rule_version_id' => $ruleVersion->id,
            'recurrence_original_start_time' => $this->toUtcDateTimeString($startTime),
            'recurrence_local_date' => $localDate,
            'recurrence_exception_kind' => $exception['kind'] ?? null,
            'recurrence_snapshot' => [
                'version' => $ruleVersion->version,
                'frequency' => $ruleVersion->frequency,
                'interval' => $ruleVersion->interval,
                'days_of_week' => $ruleVersion->days_of_week,
                'timezone' => $timezone->getName(),
                'exception' => $exception ? [
                    'kind' => $exception['kind'],
                    'name' => $exception['name'] ?? null,
                ] : null,
            ],
        ]));
    }

    private function persistSeriesRule(array $data, DateTimeZone $timezone, Carbon $baseStart, Carbon $until): array
    {
        $series = EventRecurrenceSeries::create([
            'club_id' => $data['club_id'] ?? null,
            'team_id' => $data['team_id'] ?? null,
            'created_by' => auth()->id(),
            'title' => $data['title'],
            'timezone' => $timezone->getName(),
            'starts_at' => $this->toUtcDateTimeString($baseStart),
            'ends_at' => ! empty($data['end_time']) ? $this->toUtcDateTimeString(Carbon::parse($data['end_time'], $timezone)) : null,
            'active_from' => $this->toUtcDateTimeString($baseStart),
            'active_until' => $this->toUtcDateTimeString($until),
            'current_version' => 1,
        ]);

        $rule = $series->rules()->create([
            'created_by' => auth()->id(),
            'version' => 1,
            'frequency' => $data['recurring'],
            'interval' => $data['recurring'] === 'biweekly' ? 2 : 1,
            'days_of_week' => $data['recurrence_days'] ?? null,
            'starts_at' => $this->toUtcDateTimeString($baseStart),
            'ends_at' => ! empty($data['end_time']) ? $this->toUtcDateTimeString(Carbon::parse($data['end_time'], $timezone)) : null,
            'effective_from' => $this->toUtcDateTimeString($baseStart),
            'effective_until' => $this->toUtcDateTimeString($until),
            'rule_payload' => [
                'holidays' => $data['holidays'] ?? [],
                'school_holidays' => $data['school_holidays'] ?? [],
                'blackout_windows' => $data['blackout_windows'] ?? [],
                'seasonal_adjustments' => $data['seasonal_adjustments'] ?? [],
            ],
        ]);

        return [$series, $rule];
    }

    private function normalizedExceptions(array $exceptions, DateTimeZone $timezone, EventRecurrenceRuleVersion $ruleVersion): array
    {
        $indexed = [];

        foreach ($exceptions as $exception) {
            $kind = (string) ($exception['kind'] ?? 'cancelled');
            $localDate = (string) ($exception['local_date'] ?? '');

            if ($localDate === '' || ! in_array($kind, EventRecurrenceException::KINDS, true)) {
                continue;
            }

            $stored = $ruleVersion->series->exceptions()->create([
                'rule_version_id' => $ruleVersion->id,
                'kind' => $kind,
                'local_date' => $localDate,
                'starts_at' => ! empty($exception['starts_at']) ? $this->toUtcDateTimeString(Carbon::parse($exception['starts_at'], $timezone)) : null,
                'ends_at' => ! empty($exception['ends_at']) ? $this->toUtcDateTimeString(Carbon::parse($exception['ends_at'], $timezone)) : null,
                'timezone' => $timezone->getName(),
                'name' => $exception['name'] ?? null,
                'payload' => $exception,
            ]);

            $indexed[$localDate] = [
                'kind' => $stored->kind,
                'starts_at' => $exception['starts_at'] ?? null,
                'ends_at' => $exception['ends_at'] ?? null,
                'name' => $stored->name,
            ];
        }

        return $indexed;
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

        $data['event_timezone'] = $timezone->getName();

        return $data;
    }

    private function withoutInputTimezone(array $data): array
    {
        return collect($data)->except([
            'update_scope',
            'effective_from',
            'recurrence_exceptions',
            'holidays',
            'school_holidays',
            'blackout_windows',
            'seasonal_adjustments',
        ])->all();
    }

    private function updateFutureSeries(Event $event, array $data): void
    {
        $timezone = $data['event_timezone'] ?? $event->event_timezone ?? config('app.timezone', 'UTC');
        $cutoff = Carbon::parse($data['effective_from'] ?? now(), $timezone);
        $updates = $this->normalizeEventTimes($this->withoutInputTimezone($data), $timezone);

        unset(
            $updates['recurrence_series_id'],
            $updates['recurrence_rule_version_id'],
            $updates['recurrence_original_start_time'],
            $updates['recurrence_local_date'],
            $updates['recurrence_snapshot'],
            $updates['completed_at'],
        );

        if ($updates === []) {
            return;
        }

        Event::query()
            ->where('recurrence_series_id', $event->recurrence_series_id)
            ->whereNull('completed_at')
            ->where('start_time', '>=', $this->toUtcDateTimeString($cutoff))
            ->update($updates);
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
