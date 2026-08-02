<?php

namespace App\Support;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class EventAttendance
{
    public static function canManage(User $user, Event $event): bool
    {
        if ($user->can('update', $event)) {
            return true;
        }

        if ($event->team && $event->team->users()
            ->where('users.id', $user->id)
            ->wherePivotIn('role', TeamRoles::TEAM_STAFF_ROLES)
            ->exists()) {
            return true;
        }

        $club = $event->resolvedClub();

        return (bool) $club?->users()
            ->where('users.id', $user->id)
            ->tap(fn ($query) => ClubRoles::whereAny($query, ['owner', 'admin', 'manager', 'academy_manager']))
            ->exists();
    }

    public static function allowedUserIds(Event $event): Collection
    {
        if ($event->team) {
            return $event->team->users()->pluck('users.id')->map(fn ($id) => (int) $id);
        }

        if ($club = $event->resolvedClub()) {
            return $club->users()->pluck('users.id')->map(fn ($id) => (int) $id);
        }

        return $event->participants()->pluck('users.id')->map(fn ($id) => (int) $id);
    }

    public static function assertRowsBelongToEvent(Event $event, array $rows): void
    {
        $allowedIds = self::allowedUserIds($event);
        $submittedIds = collect($rows)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $invalidIds = $submittedIds->diff($allowedIds)->values();

        if ($invalidIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'attendance' => 'Anwesenheit kann nur für Mitglieder dieses Teams oder Vereins erfasst werden.',
            ]);
        }
    }

    public static function record(Event $event, array $rows, string $responseMode = 'trainer'): void
    {
        self::assertRowsBelongToEvent($event, $rows);

        collect($rows)
            ->map(fn ($row) => [
                'user_id' => (int) $row['user_id'],
                'status' => $row['status'],
                'response_reason' => $row['response_reason'] ?? null,
            ])
            ->unique('user_id')
            ->each(fn ($row) => EventParticipant::query()->updateOrCreate(
                [
                    'event_id' => $event->id,
                    'user_id' => $row['user_id'],
                ],
                [
                    'status' => $row['status'],
                    'response_reason' => $row['response_reason'],
                    'response_mode' => $responseMode,
                    'responded_at' => now(),
                ]
            ));
    }
}
