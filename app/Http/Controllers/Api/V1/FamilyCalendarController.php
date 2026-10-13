<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\FamilyCalendarEntry;
use App\Models\User;
use App\Services\FamilyCalendarService;
use App\Support\EventAttendance;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class FamilyCalendarController extends Controller
{
    public function index(Request $request, FamilyCalendarService $calendar)
    {
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $timezone = in_array($request->user()->timezone, timezone_identifiers_list(), true)
            ? $request->user()->timezone : config('app.timezone', 'UTC');
        $month = $data['month'] ?? CarbonImmutable::now($timezone)->format('Y-m');
        $localStart = CarbonImmutable::parse($month.'-01', $timezone)->startOfMonth();
        $start = $localStart->utc();
        $end = $localStart->endOfMonth()->utc();
        $relationships = $calendar->relationships($request->user());
        $events = collect();
        $append = function (User $person, ?int $clubId = null) use ($calendar, $start, $end, &$events): void {
            $rows = $calendar->participantEvents($person)
                ->when($clubId, fn ($q) => $q->where(fn ($q) => $q->where('club_id', $clubId)->orWhereHas('team', fn ($t) => $t->where('club_id', $clubId))))
                ->whereBetween('start_time', [$start, $end])
                ->with(['participantRecords' => fn ($q) => $q->where('user_id', $person->id)])
                ->orderBy('start_time')->get()
                ->map(fn (Event $event) => [
                    'id' => $event->id, 'person_id' => $person->id, 'person_name' => $person->name,
                    'title' => $event->title, 'starts_at' => $event->start_time?->toJSON(), 'ends_at' => $event->end_time?->toJSON(),
                    'location' => $event->location_name ?: $event->location, 'status' => $event->status,
                    'response_status' => $event->participantRecords->first()?->status,
                    'can_respond' => Gate::forUser($person)->allows('join', $event) && ! $event->participant_response_deadline_at?->isPast(),
                    'participation_requirements' => $event->participation_requirements,
                    'participation_consent_required' => (bool) $event->participation_consent_required,
                    'participation_consent_version' => $event->participation_consent_version,
                    'requires_camp_details' => $event->type === 'camp' || ! empty($event->camp_groups)
                        || (bool) $event->camp_privacy_notice_version || (bool) $event->camp_travel_consent_required
                        || ! empty($event->camp_emergency_contacts),
                    'camp_groups' => $event->camp_groups ?? [],
                    'camp_privacy_notice_version' => $event->camp_privacy_notice_version,
                    'camp_travel_consent_required' => (bool) $event->camp_travel_consent_required,
                    'camp_guardian_consent_required' => (bool) $event->camp_guardian_consent_required,
                    'camp_emergency_contact_required' => ! empty($event->camp_emergency_contacts) || (bool) $event->camp_travel_consent_required,
                    'camp_group_key' => $event->participantRecords->first()?->camp_group_key,
                ]);
            $events = $events->concat($rows);
        };
        $append($request->user());
        foreach ($relationships->unique(fn ($row) => $row->child_user_id.':'.$row->club_id) as $relationship) {
            $append($relationship->child, $relationship->club_id);
        }
        $entries = FamilyCalendarEntry::where('user_id', $request->user()->id)
            ->where('starts_at', '<=', $end)->where('ends_at', '>=', $start)->orderBy('starts_at')->get()
            ->filter(fn ($entry) => ! $entry->child_user_id || $relationships->contains(fn ($row) => $row->child_user_id === $entry->child_user_id && $row->club_id === $entry->club_id))->values();

        return response()->json(['data' => [
            'month' => $month, 'self' => ['id' => $request->user()->id, 'name' => $request->user()->name],
            'children' => $relationships->map(fn ($row) => ['id' => $row->child_user_id, 'name' => $row->child->name, 'club_id' => $row->club_id])->values(),
            'events' => $events->unique(fn ($row) => $row['id'].':'.$row['person_id'])->sortBy('starts_at')->values(),
            'entries' => $entries,
        ]]);
    }

    public function store(Request $request, FamilyCalendarService $calendar)
    {
        return $this->save($request, $calendar);
    }

    public function update(Request $request, FamilyCalendarEntry $entry, FamilyCalendarService $calendar)
    {
        abort_unless($entry->user_id === $request->user()->id, 404);

        return $this->save($request, $calendar, $entry);
    }

    public function destroy(Request $request, FamilyCalendarEntry $entry)
    {
        abort_unless($entry->user_id === $request->user()->id, 404);
        $entry->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function respond(Request $request, Event $event, int $person, FamilyCalendarService $calendar, EventController $events)
    {
        return DB::transaction(function () use ($request, $event, $person, $calendar, $events) {
            $participant = $request->user();
            if ($person !== $participant->id) {
                $clubId = $event->resolvedClub()?->id;
                abort_unless($clubId, 403);
                $participant = $calendar->authorizeChild($participant, $person, $clubId);
            }
            abort_unless($calendar->participantEvents($participant)->whereKey($event->id)->exists(), 404);
            $before = EventParticipant::where('event_id', $event->id)->where('user_id', $person)->first();
            $state = $before ? EventAttendance::state($before) : null;
            $events->respondFor($request, $event, $participant);
            $saved = EventParticipant::where('event_id', $event->id)->where('user_id', $person)->firstOrFail();
            $saved->forceFill(['response_mode' => 'family'])->save();
            EventAttendance::recordCorrection($event, $saved, $state, $request->user(), 'family');

            return response()->json(['data' => ['event_id' => $event->id, 'person_id' => $person, 'response_status' => $saved->status]]);
        });
    }

    private function save(Request $request, FamilyCalendarService $calendar, ?FamilyCalendarEntry $entry = null)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'], 'location' => ['nullable', 'string', 'max:200'],
            'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'child_user_id' => ['nullable', 'integer'], 'club_id' => ['nullable', 'integer', 'required_with:child_user_id'],
        ]);
        $data['starts_at'] = CarbonImmutable::parse($data['starts_at'])->utc();
        $data['ends_at'] = CarbonImmutable::parse($data['ends_at'])->utc();
        if (! empty($data['child_user_id'])) {
            $calendar->authorizeChild($request->user(), $data['child_user_id'], $data['club_id']);
        } else {
            $data['child_user_id'] = null;
            $data['club_id'] = null;
        }
        $created = ! $entry;
        $entry ??= new FamilyCalendarEntry;
        $entry->fill($data)->fill(['user_id' => $request->user()->id])->save();

        return response()->json(['data' => $entry], $created ? 201 : 200);
    }
}
