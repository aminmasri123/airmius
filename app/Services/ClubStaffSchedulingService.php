<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubMemberQualification;
use App\Models\ClubStaffAssignment;
use App\Models\ClubStaffAvailability;
use App\Models\Event;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ClubStaffSchedulingService
{
    public function conflictsFor(Club $club, int $userId, CarbonInterface $startsAt, CarbonInterface $endsAt, ?int $ignoreAssignmentId = null, array $requiredQualifications = []): array
    {
        $conflicts = [];

        $blockingAvailability = ClubStaffAvailability::query()
            ->where('club_id', $club->id)
            ->where('user_id', $userId)
            ->whereIn('status', ['unavailable', 'tentative'])
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->orderBy('starts_at')
            ->get();

        foreach ($blockingAvailability as $availability) {
            $conflicts[] = [
                'type' => 'availability',
                'severity' => $availability->status === 'unavailable' ? 'blocking' : 'warning',
                'message' => $availability->status === 'unavailable' ? 'Member is marked unavailable.' : 'Member is only tentatively available.',
                'availability_id' => $availability->id,
                'starts_at' => $availability->starts_at?->toJSON(),
                'ends_at' => $availability->ends_at?->toJSON(),
            ];
        }

        $assignments = ClubStaffAssignment::query()
            ->where('club_id', $club->id)
            ->where('user_id', $userId)
            ->whereIn('status', ['planned', 'confirmed', 'swap_requested'])
            ->when($ignoreAssignmentId, fn ($query) => $query->whereKeyNot($ignoreAssignmentId))
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->orderBy('starts_at')
            ->get();

        foreach ($assignments as $assignment) {
            $conflicts[] = [
                'type' => 'assignment_overlap',
                'severity' => 'blocking',
                'message' => 'Member already has a staff assignment in this time window.',
                'assignment_id' => $assignment->id,
                'event_id' => $assignment->event_id,
                'role' => $assignment->role,
                'starts_at' => $assignment->starts_at?->toJSON(),
                'ends_at' => $assignment->ends_at?->toJSON(),
            ];
        }

        $events = Event::query()
            ->where('club_id', $club->id)
            ->where('status', '!=', 'cancelled')
            ->whereHas('participants', fn ($query) => $query
                ->where('users.id', $userId)
                ->whereIn('event_participants.status', ['yes', 'late']))
            ->where('start_time', '<', $endsAt)
            ->where('end_time', '>', $startsAt)
            ->orderBy('start_time')
            ->get();

        foreach ($events as $event) {
            $conflicts[] = [
                'type' => 'event_overlap',
                'severity' => 'warning',
                'message' => 'Member is already registered for an event in this time window.',
                'event_id' => $event->id,
                'title' => $event->title,
                'starts_at' => $event->start_time?->toJSON(),
                'ends_at' => $event->end_time?->toJSON(),
            ];
        }

        foreach ($this->missingQualifications($club, $userId, $startsAt, $requiredQualifications) as $title) {
            $conflicts[] = [
                'type' => 'qualification_missing',
                'severity' => 'blocking',
                'message' => 'Required qualification is missing or not verified for the assignment date.',
                'qualification' => $title,
            ];
        }

        return $conflicts;
    }

    public function calendarFor(Club $club, CarbonInterface $startsAt, CarbonInterface $endsAt): array
    {
        return [
            'availabilities' => ClubStaffAvailability::query()
                ->where('club_id', $club->id)
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->orderBy('starts_at')
                ->get()
                ->map(fn (ClubStaffAvailability $availability) => $this->availabilityPayload($availability))
                ->values(),
            'assignments' => ClubStaffAssignment::query()
                ->with(['event:id,title', 'team:id,name'])
                ->where('club_id', $club->id)
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->orderBy('starts_at')
                ->get()
                ->map(fn (ClubStaffAssignment $assignment) => $this->assignmentPayload($assignment))
                ->values(),
            'unfilled_assignments' => ClubStaffAssignment::query()
                ->with(['event:id,title', 'team:id,name'])
                ->where('club_id', $club->id)
                ->whereNull('user_id')
                ->whereIn('status', ['open', 'planned'])
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->orderBy('starts_at')
                ->get()
                ->map(fn (ClubStaffAssignment $assignment) => $this->assignmentPayload($assignment))
                ->values(),
        ];
    }

    public function availabilityPayload(ClubStaffAvailability $availability): array
    {
        return [
            'id' => $availability->id,
            'club_id' => $availability->club_id,
            'user_id' => $availability->user_id,
            'starts_at' => $availability->starts_at?->toJSON(),
            'ends_at' => $availability->ends_at?->toJSON(),
            'status' => $availability->status,
            'source' => $availability->source,
            'note' => $availability->note,
        ];
    }

    public function assignmentPayload(ClubStaffAssignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'club_id' => $assignment->club_id,
            'event_id' => $assignment->event_id,
            'event_title' => $assignment->event?->title,
            'team_id' => $assignment->team_id,
            'team_name' => $assignment->team?->name,
            'user_id' => $assignment->user_id,
            'substitute_user_id' => $assignment->substitute_user_id,
            'starts_at' => $assignment->starts_at?->toJSON(),
            'ends_at' => $assignment->ends_at?->toJSON(),
            'role' => $assignment->role,
            'required_qualifications' => $assignment->required_qualifications ?? [],
            'status' => $assignment->status,
            'note' => $assignment->note,
        ];
    }

    private function missingQualifications(Club $club, int $userId, CarbonInterface $assignmentDate, array $requiredQualifications): Collection
    {
        $required = collect($requiredQualifications)
            ->filter(fn ($title) => is_string($title) && trim($title) !== '')
            ->map(fn ($title) => trim($title))
            ->unique()
            ->values();

        if ($required->isEmpty()) {
            return collect();
        }

        $verified = ClubMemberQualification::query()
            ->where('club_id', $club->id)
            ->where('user_id', $userId)
            ->where('proof_status', 'verified')
            ->whereIn('title', $required->all())
            ->where(fn ($query) => $query->whereNull('valid_from')->orWhere('valid_from', '<=', $assignmentDate->toDateString()))
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhere('valid_until', '>=', $assignmentDate->toDateString()))
            ->pluck('title');

        return $required->diff($verified);
    }
}
