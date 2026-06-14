<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EventResource;
use App\Models\Club;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Sport;
use App\Models\Team;
use App\Models\User;
use App\Services\EventService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EventController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private EventService $service) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', Rule::in(Event::TYPES)],
            'visibility' => ['nullable', Rule::in(Event::VISIBILITIES)],
            'club_id' => ['nullable', 'integer', 'exists:clubs,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'period' => ['nullable', Rule::in(['upcoming', 'past', 'all'])],
            'calendar_month' => ['nullable', 'date_format:Y-m'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = $this->decorateEvents($this->visibleEvents($request), $request)
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('title', 'like', '%'.$search.'%')
                        ->orWhere('location', 'like', '%'.$search.'%')
                        ->orWhere('location_name', 'like', '%'.$search.'%')
                        ->orWhere('location_city', 'like', '%'.$search.'%')
                        ->orWhere('notes', 'like', '%'.$search.'%')
                        ->orWhereHas('team', fn ($teamQuery) => $teamQuery->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('club', fn ($clubQuery) => $clubQuery->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['visibility'] ?? null, fn ($query, $visibility) => $query->where('visibility', $visibility))
            ->when($filters['club_id'] ?? null, fn ($query, $clubId) => $query->where('club_id', $clubId))
            ->when($filters['team_id'] ?? null, fn ($query, $teamId) => $query->where('team_id', $teamId))
            ->when(($filters['period'] ?? null) === 'upcoming', fn ($query) => $query->where('start_time', '>=', now()->startOfDay()))
            ->when(($filters['period'] ?? null) === 'past', fn ($query) => $query->where('start_time', '<', now()->startOfDay()))
            ->when(($filters['period'] ?? null) === null && $request->filled('from'), fn ($query) => $query->where('start_time', '>=', $request->date('from')))
            ->when(($filters['period'] ?? null) === null && $request->filled('to'), fn ($query) => $query->where('start_time', '<=', $request->date('to')))
            ->orderBy('start_time');

        $calendarMonth = CarbonImmutable::createFromFormat('Y-m-d', ($filters['calendar_month'] ?? now()->format('Y-m')).'-01')
            ?: CarbonImmutable::now();
        $calendarStart = $calendarMonth->startOfMonth()->startOfWeek(1);
        $calendarEnd = $calendarStart->addDays(41)->endOfDay();

        $events = (clone $query)->paginate($this->perPage($request));
        $calendarEvents = (clone $query)
            ->whereBetween('start_time', [$calendarStart, $calendarEnd])
            ->limit(500)
            ->get();
        $nextEvent = (clone $query)
            ->where('start_time', '>=', now())
            ->where('status', '!=', 'cancelled')
            ->first();

        return response()->json([
            'data' => EventResource::collection($events->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
            'calendar_events' => EventResource::collection($calendarEvents)->resolve($request),
            'event_stats' => [
                'upcoming' => (clone $query)->where('start_time', '>=', now())->where('status', '!=', 'cancelled')->count(),
                'today' => (clone $query)->whereBetween('start_time', [now()->startOfDay(), now()->endOfDay()])->count(),
                'cancelled' => (clone $query)->where('status', 'cancelled')->count(),
            ],
            'next_event' => $nextEvent ? (new EventResource($nextEvent))->resolve($request) : null,
            'calendar' => [
                'month' => $calendarMonth->format('Y-m'),
                'start' => $calendarStart->toDateString(),
                'end' => $calendarEnd->toDateString(),
            ],
            'clubs' => Club::query()
                ->whereHas('users', fn ($query) => $query->where('users.id', $request->user()->id))
                ->select(['id', 'name', 'city'])
                ->orderBy('name')
                ->get(),
            'teams' => Team::query()
                ->whereHas('users', fn ($query) => $query->where('users.id', $request->user()->id))
                ->select(['id', 'club_id', 'name'])
                ->orderBy('name')
                ->get(),
            'event_types' => Event::TYPES,
            'visibilities' => Event::VISIBILITIES,
            'participant_statuses' => Event::PARTICIPANT_STATUSES,
            'sports' => Sport::query()->select(['id', 'name', 'slug'])->orderBy('name')->get(),
            'event_creation' => $this->eventCreationLimitsFor($request->user()),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'type' => $filters['type'] ?? '',
                'visibility' => $filters['visibility'] ?? '',
                'club_id' => $filters['club_id'] ?? '',
                'team_id' => $filters['team_id'] ?? '',
                'period' => $filters['period'] ?? 'upcoming',
                'calendar_month' => $calendarMonth->format('Y-m'),
            ],
        ]);
    }

    public function show(Request $request, Event $event)
    {
        abort_unless($this->visibleEvents($request)->whereKey($event->id)->exists(), 404);

        return new EventResource(
            $this->decorateEvents(Event::query()->whereKey($event->id), $request)->firstOrFail()
        );
    }

    public function store(Request $request)
    {
        $this->authorize('create', Event::class);

        $data = $request->validate([
            'club_id' => ['nullable', 'exists:clubs,id'],
            'team_id' => ['nullable', 'exists:teams,id'],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(Event::TYPES)],
            'visibility' => ['required', Rule::in(Event::VISIBILITIES)],
            'start_time' => ['required', 'date'],
            'end_time' => ['nullable', 'date', 'after_or_equal:start_time'],
            'location' => ['nullable', 'string', 'max:255'],
            'location_name' => ['nullable', 'string', 'max:255'],
            'location_street' => ['nullable', 'string', 'max:255'],
            'location_house_number' => ['nullable', 'string', 'max:40'],
            'location_postal_code' => ['nullable', 'string', 'max:20'],
            'location_city' => ['nullable', 'string', 'max:255'],
            'location_country' => ['nullable', 'string', 'size:2'],
            'location_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'location_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'max_participants' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'notes' => ['nullable', 'string'],
            'event_timezone' => ['nullable', 'timezone'],
        ]);

        $data['status'] = 'scheduled';
        $data['event_timezone'] ??= config('app.timezone', 'UTC');

        if (($data['visibility'] ?? null) === 'public') {
            $data['club_id'] = null;
            $data['team_id'] = null;
        }

        if (($data['visibility'] ?? null) === 'organization') {
            $data['team_id'] = null;
        }

        if (($data['visibility'] ?? null) === 'private') {
            $data['club_id'] = null;
        }

        if (! empty($data['team_id'])) {
            $team = Team::query()
                ->whereHas('users', fn ($query) => $query->where('users.id', $request->user()->id))
                ->findOrFail($data['team_id']);

            $data['club_id'] = $team->club_id;
        }

        if (! empty($data['club_id'])) {
            Club::query()
                ->whereHas('users', fn ($query) => $query->where('users.id', $request->user()->id))
                ->findOrFail($data['club_id']);
        }

        if ($data['visibility'] === 'private' && empty($data['team_id'])) {
            throw ValidationException::withMessages([
                'team_id' => 'Private Events brauchen ein Team.',
            ]);
        }

        if ($data['visibility'] === 'organization' && empty($data['club_id'])) {
            throw ValidationException::withMessages([
                'club_id' => 'Vereins-Events brauchen einen Verein.',
            ]);
        }

        $event = $this->service->create($data);

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

    private function eventCreationLimitsFor(User $user): array
    {
        $isLimited = ! $this->hasUnlimitedEventCreation($user);
        $used = Event::query()
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();
        $limit = 2;

        return [
            'is_free_limited' => $isLimited,
            'monthly_limit' => $isLimited ? $limit : null,
            'used_this_month' => $isLimited ? $used : null,
            'remaining_this_month' => $isLimited ? max(0, $limit - $used) : null,
            'allows_recurring' => ! $isLimited,
        ];
    }

    private function hasUnlimitedEventCreation(User $user): bool
    {
        if (
            $user->can('event.create')
            || $user->hasAnyRole(['coach', 'assistant_coach', 'performance_coach', 'fitness_coach', 'club_owner', 'club_admin', 'club_manager', 'academy_manager'])
        ) {
            return true;
        }

        return $user->subscriptions()
            ->whereIn('status', ['active', 'trialing'])
            ->whereHas('plan', fn ($query) => $query->where('slug', '!=', 'free'))
            ->exists();
    }
}
