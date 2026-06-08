<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EventResource;
use App\Models\Event;
use App\Models\EventParticipant;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $events = $this->decorateEvents($this->visibleEvents($request), $request)
            ->when($request->filled('from'), fn ($query) => $query->where('start_time', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->where('start_time', '<=', $request->date('to')))
            ->orderBy('start_time')
            ->paginate($this->perPage($request));

        return EventResource::collection($events);
    }

    public function show(Request $request, Event $event)
    {
        abort_unless($this->visibleEvents($request)->whereKey($event->id)->exists(), 404);

        return new EventResource(
            $this->decorateEvents(Event::query()->whereKey($event->id), $request)->firstOrFail()
        );
    }

    public function respond(Request $request, Event $event)
    {
        abort_unless($this->visibleEvents($request)->whereKey($event->id)->exists(), 404);
        $this->authorize('join', $event);

        $data = $request->validate([
            'status' => ['required', Rule::in(Event::PARTICIPANT_STATUSES)],
            'response_reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($data['status'] === 'yes' && $event->max_participants) {
            $alreadyYes = EventParticipant::query()
                ->where('event_id', $event->id)
                ->where('user_id', $request->user()->id)
                ->where('status', 'yes')
                ->exists();

            $yesCount = EventParticipant::query()
                ->where('event_id', $event->id)
                ->where('status', 'yes')
                ->count();

            abort_if(! $alreadyYes && $yesCount >= $event->max_participants, 422, 'Event is full.');
        }

        EventParticipant::query()->updateOrCreate(
            [
                'event_id' => $event->id,
                'user_id' => $request->user()->id,
            ],
            [
                'status' => $data['status'],
                'response_reason' => $data['response_reason'] ?? null,
                'response_mode' => 'mobile',
                'responded_at' => now(),
            ]
        );

        return new EventResource($this->decorateEvents(Event::query()->whereKey($event->id), $request)->firstOrFail());
    }

    public function leave(Request $request, Event $event)
    {
        abort_unless($this->visibleEvents($request)->whereKey($event->id)->exists(), 404);
        $this->authorize('join', $event);

        EventParticipant::query()
            ->where('event_id', $event->id)
            ->where('user_id', $request->user()->id)
            ->delete();

        return new EventResource($this->decorateEvents(Event::query()->whereKey($event->id), $request)->firstOrFail());
    }

    private function visibleEvents(Request $request)
    {
        $user = $request->user();
        $clubIds = $user->clubs()->pluck('clubs.id')->all();
        $teamIds = $user->teams()->pluck('teams.id')->all();

        return Event::query()->where(function ($query) use ($user, $clubIds, $teamIds) {
            $query
                ->where('visibility', 'public')
                ->orWhere('user_id', $user->id)
                ->orWhereIn('club_id', $clubIds)
                ->orWhereIn('team_id', $teamIds)
                ->orWhereHas('participants', fn ($participants) => $participants->where('users.id', $user->id));
        });
    }

    private function decorateEvents($query, Request $request)
    {
        $user = $request->user();

        return $query
            ->with(['club', 'team', 'user'])
            ->withCount([
                'participants',
                'comments',
                'participants as yes_count' => fn ($participants) => $participants->where('event_participants.status', 'yes'),
                'participants as maybe_count' => fn ($participants) => $participants->where('event_participants.status', 'maybe'),
                'participants as no_count' => fn ($participants) => $participants->where('event_participants.status', 'no'),
            ])
            ->addSelect([
                'my_participation_status' => EventParticipant::query()
                    ->select('status')
                    ->whereColumn('event_participants.event_id', 'events.id')
                    ->where('user_id', $user->id)
                    ->limit(1),
            ]);
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->integer('per_page', 20), 1), 50);
    }
}
