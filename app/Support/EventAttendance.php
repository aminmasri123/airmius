<?php

namespace App\Support;

use App\Models\Event;
use App\Models\EventAttendanceCorrection;
use App\Models\EventParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class EventAttendance
{
    public static function canManage(User $user, Event $event): bool
    {
        if ($user->can('update', $event)) {
            return true;
        }

        $club = $event->resolvedClub();

        if ($event->team
            && ! ($club && ClubPermissions::explicitlyDenies($club, $user, ClubPermissions::EVENTS_EDIT))
            && $event->team->users()
                ->where('users.id', $user->id)
                ->wherePivotIn('role', TeamRoles::TEAM_STAFF_ROLES)
                ->exists()) {
            return true;
        }

        return $club
            ? ClubPermissions::allows($club, $user, ClubPermissions::EVENTS_EDIT)
            : false;
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

    public static function record(Event $event, array $rows, string $responseMode = 'trainer', ?User $actor = null, ?string $reasonCode = null): void
    {
        self::assertRowsBelongToEvent($event, $rows);

        DB::transaction(function () use ($event, $rows, $responseMode, $actor, $reasonCode): void {
            collect($rows)
                ->map(fn ($row) => self::normalizeRow($row))
                ->unique('user_id')
                ->each(function ($row) use ($event, $responseMode, $actor, $reasonCode): void {
                    $participant = EventParticipant::query()
                        ->where('event_id', $event->id)
                        ->where('user_id', $row['user_id'])
                        ->lockForUpdate()
                        ->first();
                    $before = $participant ? self::state($participant) : null;

                    $values = [
                        'status' => $row['status'],
                        'rsvp_status' => $row['rsvp_status'],
                        'attendance_status' => $row['attendance_status'],
                        'response_reason' => $row['response_reason'],
                        'absence_reason' => $row['absence_reason'],
                        'response_mode' => $responseMode,
                        'responded_at' => now(),
                    ];

                    if ($participant) {
                        $participant->forceFill($values)->save();
                    } else {
                        $participant = EventParticipant::query()->create([
                            'event_id' => $event->id,
                            'user_id' => $row['user_id'],
                            ...$values,
                        ]);
                    }

                    self::recordCorrection($event, $participant, $before, $actor, $responseMode, $reasonCode);
                });
        });
    }

    public static function recordCorrection(
        Event $event,
        EventParticipant $participant,
        ?array $before,
        ?User $actor,
        string $source,
        ?string $reasonCode = null
    ): void {
        $after = self::state($participant);
        $changed = collect(array_keys($after))
            ->filter(fn (string $key) => ($before[$key] ?? null) !== ($after[$key] ?? null))
            ->values()
            ->all();

        if ($before !== null && $changed === []) {
            return;
        }

        EventAttendanceCorrection::query()->create([
            'event_id' => $event->id,
            'user_id' => $participant->user_id,
            'actor_id' => $actor?->id,
            'source' => $source,
            'before_state' => $before ? self::auditState($before) : null,
            'after_state' => self::auditState($after),
            'changed_fields' => $before ? $changed : array_keys($after),
            'reason_code' => $reasonCode,
            'contains_private_note' => ($participant->response_reason || $participant->absence_reason),
        ]);

        if ($club = $event->resolvedClub()) {
            ClubAuditLog::record($club, $actor, 'club.event.attendance_corrected', $participant, [
                'event_id' => $event->id,
                'user_id' => $participant->user_id,
                'source' => $source,
                'changed_fields' => $before ? $changed : array_keys($after),
                'reason_code' => $reasonCode,
                'contains_private_note' => (bool) ($participant->response_reason || $participant->absence_reason),
            ]);
        }
    }

    public static function state(EventParticipant $participant): array
    {
        return [
            'status' => $participant->status,
            'rsvp_status' => $participant->rsvp_status,
            'attendance_status' => $participant->attendance_status,
            'check_in_method' => $participant->check_in_method,
            'checked_in_at' => $participant->checked_in_at?->toJSON(),
            'response_reason' => $participant->response_reason,
            'absence_reason' => $participant->absence_reason,
        ];
    }

    private static function auditState(array $state): array
    {
        unset($state['response_reason'], $state['absence_reason']);

        return $state;
    }

    private static function normalizeRow(array $row): array
    {
        $legacyStatus = $row['status'] ?? null;
        $attendanceStatus = $row['attendance_status'] ?? match ($legacyStatus) {
            'yes' => 'present',
            'late' => 'late',
            'no' => 'absent',
            default => null,
        };
        $rsvpStatus = $row['rsvp_status'] ?? match ($legacyStatus) {
            'yes', 'maybe', 'no', 'waitlist' => $legacyStatus,
            default => null,
        };
        $status = $legacyStatus ?? self::legacyStatus($rsvpStatus, $attendanceStatus);

        return [
            'user_id' => (int) $row['user_id'],
            'status' => $status,
            'rsvp_status' => $rsvpStatus,
            'attendance_status' => $attendanceStatus,
            'response_reason' => $row['response_reason'] ?? null,
            'absence_reason' => $row['absence_reason'] ?? null,
        ];
    }

    private static function legacyStatus(?string $rsvpStatus, ?string $attendanceStatus): string
    {
        return match ($attendanceStatus) {
            'present' => 'yes',
            'late' => 'late',
            'absent', 'excused' => 'no',
            default => $rsvpStatus ?: 'maybe',
        };
    }
}
