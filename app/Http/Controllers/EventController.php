<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\Sport;
use App\Models\Team;
use App\Models\TeamPenaltyRule;
use App\Models\User;
use App\Services\EventService;
use App\Services\GamificationService;
use App\Support\AppNotification;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class EventController extends Controller
{
    use AuthorizesRequests;

    private const MAX_RECURRING_EVENTS = 370;

    public function __construct(
        private EventService $service,
        private GamificationService $gamification,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Event::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', Rule::in(Event::TYPES)],
            'visibility' => ['nullable', Rule::in(Event::VISIBILITIES)],
            'club_id' => ['nullable', 'integer', 'exists:clubs,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'period' => ['nullable', Rule::in(['upcoming', 'past', 'all'])],
            'radius_km' => ['nullable', 'integer', 'min:1', 'max:500'],
            'sport_ids' => ['nullable', 'array'],
            'sport_ids.*' => ['integer', 'exists:sports,id'],
            'calendar_month' => ['nullable', 'date_format:Y-m'],
        ]);

        $calendarMonthFilter = $filters['calendar_month'] ?? null;
        $filterKeys = ['search', 'type', 'visibility', 'club_id', 'team_id', 'period', 'radius_km', 'sport_ids'];
        $hasManualFilters = collect($filterKeys)->contains(fn ($key) => $request->query->has($key));
        $filters = $this->normalizeEventFilters($hasManualFilters
            ? $filters
            : array_merge($this->eventDefaultFiltersFor($request->user()), $filters));
        $filters['calendar_month'] = $calendarMonthFilter;

        $eventsQuery = Event::query()
            ->with([
                'user:id,name',
                'team:id,name,club_id,sport_type',
                'team.club:id,name,sport_type,country,street,house_number,postal_code,city,state',
                'club:id,name,sport_type,country,street,house_number,postal_code,city,state',
                'conversation:id',
                'participants' => fn ($query) => $query
                    ->where('users.id', $request->user()->id)
                    ->select('users.id', 'name'),
                'cancelledBy:id,name',
            ])
            ->withCount([
                'comments',
                'participants',
                'participantRecords as accepted_participants_count' => fn ($query) => $query->where('status', 'yes'),
            ])
            ->where(function ($query) use ($request) {
                $query->where('visibility', 'public')
                    ->orWhereHas('team.users', fn ($q) => $q->where('users.id', $request->user()->id))
                    ->orWhereHas('club.users', fn ($q) => $q->where('users.id', $request->user()->id))
                    ->orWhereHas('team.club.users', fn ($q) => $q->where('users.id', $request->user()->id));
            })
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('title', 'like', '%'.$search.'%')
                        ->orWhere('location', 'like', '%'.$search.'%')
                        ->orWhere('location_name', 'like', '%'.$search.'%')
                        ->orWhere('location_street', 'like', '%'.$search.'%')
                        ->orWhere('location_postal_code', 'like', '%'.$search.'%')
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
            ->when(($filters['period'] ?? 'upcoming') === 'upcoming', fn ($query) => $query->where('start_time', '>=', now()->startOfDay()))
            ->when(($filters['period'] ?? 'upcoming') === 'past', fn ($query) => $query->where('start_time', '<', now()->startOfDay()))
            ->orderBy('start_time');

        $this->applyEventSportFilter($eventsQuery, $filters['sport_ids'] ?? []);
        $this->applyEventLocationFilter($eventsQuery, $request->user(), $filters['radius_km'] ?? null);

        $calendarMonth = CarbonImmutable::createFromFormat('Y-m-d', ($filters['calendar_month'] ?? now()->format('Y-m')).'-01')
            ?: CarbonImmutable::now();
        $calendarStart = $calendarMonth->startOfMonth()->startOfWeek(1);
        $calendarEnd = $calendarStart->addDays(41)->endOfDay();

        $events = (clone $eventsQuery)
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Event $event) => $this->decorateEventForIndex($event, $request));

        $calendarEvents = (clone $eventsQuery)
            ->whereBetween('start_time', [$calendarStart, $calendarEnd])
            ->limit(500)
            ->get()
            ->map(fn (Event $event) => $this->decorateEventForIndex($event, $request));

        $nextEvent = (clone $eventsQuery)
            ->where('start_time', '>=', now())
            ->where('status', '!=', 'cancelled')
            ->first();

        return Inertia::render('Auth/Dashboard/Events/Index', [
            'events' => $events,
            'calendarEvents' => $calendarEvents,
            'eventStats' => [
                'upcoming' => (clone $eventsQuery)
                    ->where('start_time', '>=', now())
                    ->where('status', '!=', 'cancelled')
                    ->count(),
                'today' => (clone $eventsQuery)
                    ->whereBetween('start_time', [now()->startOfDay(), now()->endOfDay()])
                    ->count(),
                'cancelled' => (clone $eventsQuery)
                    ->where('status', 'cancelled')
                    ->count(),
            ],
            'nextEvent' => $nextEvent ? $this->decorateEventForIndex($nextEvent, $request) : null,
            'calendar' => [
                'month' => $calendarMonth->format('Y-m'),
                'start' => $calendarStart->toDateString(),
                'end' => $calendarEnd->toDateString(),
            ],
            'clubs' => Club::query()
                ->whereHas('users', fn ($query) => $query->where('users.id', $request->user()->id))
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get(),
            'teams' => Team::query()
                ->whereHas('users', fn ($query) => $query->where('users.id', $request->user()->id))
                ->select(['id', 'club_id', 'name'])
                ->orderBy('name')
                ->get(),
            'eventTypes' => Event::TYPES,
            'visibilities' => Event::VISIBILITIES,
            'participantStatuses' => Event::PARTICIPANT_STATUSES,
            'sports' => $this->sportsForFilters(),
            'eventDefaults' => $this->eventDefaultFiltersFor($request->user()),
            'eventCreation' => $this->eventCreationLimitsFor($request->user()),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'type' => $filters['type'] ?? '',
                'visibility' => $filters['visibility'] ?? '',
                'club_id' => $filters['club_id'] ?? '',
                'team_id' => $filters['team_id'] ?? '',
                'period' => $filters['period'] ?? 'upcoming',
                'radius_km' => $filters['radius_km'] ?? '',
                'sport_ids' => $filters['sport_ids'] ?? [],
                'calendar_month' => $calendarMonth->format('Y-m'),
            ],
        ]);
    }

    public function saveDefaultFilters(Request $request)
    {
        $data = $this->normalizeEventFilters($request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', Rule::in(Event::TYPES)],
            'visibility' => ['nullable', Rule::in(Event::VISIBILITIES)],
            'club_id' => ['nullable', 'integer', 'exists:clubs,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'period' => ['nullable', Rule::in(['upcoming', 'past', 'all'])],
            'radius_km' => ['nullable', 'integer', 'min:1', 'max:500'],
            'sport_ids' => ['nullable', 'array'],
            'sport_ids.*' => ['integer', 'exists:sports,id'],
        ]));

        $request->user()->update([
            'event_radius_km' => $data['radius_km'] ?: null,
            'event_default_sport_ids' => $data['sport_ids'],
            'event_default_filters' => $data,
        ]);

        return back()->with('success', 'Event-Standardfilter gespeichert.');
    }

    private function decorateEventForIndex(Event $event, Request $request): Event
    {
        $event->setAttribute('current_participant_status', $event->participants->first()?->pivot?->status);
        $event->setAttribute('can_update', $request->user()->can('update', $event));
        $event->setAttribute('can_delete', $request->user()->can('delete', $event));
        $event->setAttribute('can_cancel', $request->user()->can('cancel', $event));

        return $event;
    }

    public function show(Event $event)
    {
        $this->authorize('view', $event);

        $event->load([
            'user:id,name',
            'team:id,name,club_id,sport_type',
            'club:id,name',
            'conversation:id',
            'cancelledBy:id,name',
            'participants:id,name,email,profile_photo_path',
            'comments' => fn ($query) => $query->with('user:id,name')->latest(),
            'penaltyFees' => fn ($query) => $query
                ->with(['member:id,name,email,profile_photo_path', 'collector:id,name,email', 'penaltyRule'])
                ->latest('id'),
        ]);
        $event->loadCount([
            'participantRecords as accepted_participants_count' => fn ($query) => $query->where('status', 'yes'),
        ]);

        return Inertia::render('Auth/Dashboard/Events/Show', [
            'event' => $event,
            'clubs' => Club::query()
                ->whereHas('users', fn ($query) => $query->where('users.id', auth()->id()))
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get(),
            'teams' => Team::query()
                ->whereHas('users', fn ($query) => $query->where('users.id', auth()->id()))
                ->select(['id', 'club_id', 'name'])
                ->orderBy('name')
                ->get(),
            'eventTypes' => Event::TYPES,
            'visibilities' => Event::VISIBILITIES,
            'participantStatuses' => Event::PARTICIPANT_STATUSES,
            'currentParticipantStatus' => $event->participants()
                ->where('users.id', auth()->id())
                ->first()?->pivot?->status,
            'can' => [
                'update' => auth()->user()->can('update', $event),
                'delete' => auth()->user()->can('delete', $event),
                'cancel' => auth()->user()->can('cancel', $event),
                'manage_penalties' => $event->team
                    ? $this->canManageTeamCashbox(auth()->user(), $event->team)
                    : false,
            ],
            'penaltyCatalog' => $event->team ? [
                'can_manage' => $this->canManageTeamCashbox(auth()->user(), $event->team),
                'rules' => $event->team->penaltyRules()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('title')
                    ->get()
                    ->map(fn (TeamPenaltyRule $rule) => [
                        'id' => $rule->id,
                        'team_id' => $rule->team_id,
                        'title' => $rule->title,
                        'trigger' => $rule->trigger,
                        'calculation_type' => $rule->calculation_type,
                        'amount' => $rule->amount,
                        'currency' => $rule->currency,
                        'threshold_minutes' => $rule->threshold_minutes,
                        'max_amount' => $rule->max_amount,
                        'unit_label' => $rule->unit_label,
                    ])
                    ->values(),
                'fees' => $event->penaltyFees
                    ->map(fn ($fee) => [
                        'id' => $fee->id,
                        'team_id' => $fee->team_id,
                        'event_id' => $fee->event_id,
                        'user_id' => $fee->user_id,
                        'penalty_rule_id' => $fee->penalty_rule_id,
                        'amount' => $fee->amount,
                        'currency' => $fee->currency,
                        'status' => $fee->status,
                        'note' => $fee->note,
                        'due_date' => $fee->due_date?->toDateString(),
                        'paid_at' => $fee->paid_at?->toDateString(),
                        'member' => $fee->member ? [
                            'id' => $fee->member->id,
                            'name' => $fee->member->name,
                            'email' => $fee->member->email,
                        ] : null,
                        'rule' => $fee->penaltyRule ? [
                            'id' => $fee->penaltyRule->id,
                            'title' => $fee->penaltyRule->title,
                            'calculation_type' => $fee->penaltyRule->calculation_type,
                            'unit_label' => $fee->penaltyRule->unit_label,
                        ] : null,
                    ])
                    ->values(),
            ] : null,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Event::class);

        $data = $this->validated($request);
        $this->enforceEventCreationLimits($request, $data);

        $event = $this->service->create($data);
        $this->grantGamificationForEvent($request, $event);

        return redirect()->route('auth.events.show', $event)->with('success', 'Event erstellt.');
    }

    public function update(Request $request, Event $event)
    {
        $this->authorize('update', $event);

        $this->service->update($event, $this->validated($request));

        return back()->with('success', 'Event aktualisiert.');
    }

    public function destroy(Event $event)
    {
        $this->authorize('delete', $event);

        $this->notifyEventCancellationRecipients($event, 'deleted');

        $this->service->delete($event);

        return redirect()->route('auth.events.index')->with('success', 'Event gelöscht.');
    }

    public function cancel(Request $request, Event $event)
    {
        $this->authorize('cancel', $event);

        if ($event->status === 'cancelled') {
            return back()->with('success', 'Event ist bereits abgesagt.');
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $event->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => $request->user()->id,
            'cancellation_reason' => $data['reason'] ?? null,
        ])->save();

        $this->notifyEventCancellationRecipients($event, 'cancelled', $data['reason'] ?? null);

        return back()->with('success', 'Event wurde abgesagt und Teilnehmer wurden informiert.');
    }

    public function join(Request $request, Event $event)
    {
        $this->authorize('join', $event);

        $data = $request->validate([
            'status' => ['required', Rule::in(Event::PARTICIPANT_STATUSES)],
        ]);

        $currentStatus = $event->participants()
            ->whereKey($request->user()->id)
            ->first()
            ?->pivot
            ?->status;

        if ($currentStatus === $data['status']) {
            $event->participants()->detach($request->user()->id);

            return back()->with('success', 'Teilnahmemeldung entfernt.');
        }

        if ($data['status'] === 'yes' && $currentStatus !== 'yes' && $event->max_participants) {
            $acceptedCount = $event->participantRecords()
                ->where('status', 'yes')
                ->count();

            if ($acceptedCount >= $event->max_participants) {
                throw ValidationException::withMessages([
                    'status' => 'Dieses Event ist bereits voll.',
                ]);
            }
        }

        $event->participants()->syncWithoutDetaching([
            $request->user()->id => ['status' => $data['status']],
        ]);

        if ($data['status'] === 'yes') {
            $this->gamification->grant($request->user(), 'training_accepted', $event, [
                'event_id' => $event->id,
                'event_type' => $event->type,
            ]);
        }

        return back()->with('success', 'Teilnahmestatus gespeichert.');
    }

    public function leave(Request $request, Event $event)
    {
        $event->participants()->detach($request->user()->id);

        return back()->with('success', 'Teilnahme entfernt.');
    }

    public function comment(Request $request, Event $event)
    {
        $this->authorize('view', $event);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:1500'],
        ]);

        $comment = $event->comments()->create([
            'user_id' => $request->user()->id,
            'content' => $data['content'],
        ]);

        $this->notifyEventCommentRecipients($event, $comment, $request->user());

        return back()->with('success', 'Kommentar erstellt.');
    }

    public function chat(Event $event)
    {
        $this->authorize('view', $event);

        $conversationId = $event->conversation_id ?: Conversation::query()
            ->where('type', 'team')
            ->where('team_id', $event->team_id)
            ->value('conversations.id');

        abort_unless($conversationId, 404);

        return redirect()->route('auth.conversations.index', [
            'conversation' => $conversationId,
        ]);
    }

    private function validated(Request $request): array
    {
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
            'uses_penalty_catalog' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'recurring' => ['nullable', Rule::in(['daily', 'weekly', 'biweekly', 'monthly'])],
            'recurrence_days' => ['nullable', 'array'],
            'recurrence_days.*' => ['integer', Rule::in([0, 1, 2, 3, 4, 5, 6])],
            'recurrence_ends_at' => ['nullable', 'date'],
            'reminder_at' => ['nullable', 'date', 'before_or_equal:start_time'],
            'event_timezone' => ['nullable', 'timezone'],
        ]);

        if (in_array($data['recurring'] ?? null, ['weekly', 'biweekly'], true) && empty($data['recurrence_days'])) {
            throw ValidationException::withMessages([
                'recurrence_days' => 'Bitte mindestens einen Wochentag auswählen.',
            ]);
        }

        if (filled($data['recurring'] ?? null) && empty($data['recurrence_ends_at'])) {
            throw ValidationException::withMessages([
                'recurrence_ends_at' => 'Bitte ein Enddatum für die Wiederholung angeben.',
            ]);
        }

        $data = $this->normalizeStructuredLocation($data);

        if (! empty($data['recurrence_ends_at'])) {
            $startDate = CarbonImmutable::parse($data['start_time'])->startOfDay();
            $endDate = CarbonImmutable::parse($data['recurrence_ends_at'])->startOfDay();

            if ($endDate->lt($startDate)) {
                throw ValidationException::withMessages([
                    'recurrence_ends_at' => 'Das Wiederholungsende muss nach dem Startdatum liegen.',
                ]);
            }

            if (filled($data['recurring'] ?? null) && $this->estimatedRecurringEventCount($data) > self::MAX_RECURRING_EVENTS) {
                throw ValidationException::withMessages([
                    'recurrence_ends_at' => 'Eine Serie darf maximal '.self::MAX_RECURRING_EVENTS.' Termine erzeugen.',
                ]);
            }
        }

        if (($data['visibility'] ?? null) === 'public') {
            $data['club_id'] = null;
            $data['team_id'] = null;
            $data['uses_penalty_catalog'] = false;
        }

        if (($data['visibility'] ?? null) === 'organization') {
            $data['team_id'] = null;
            $data['uses_penalty_catalog'] = false;
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

        abort_if($data['visibility'] === 'private' && empty($data['team_id']), 422, 'Private Events brauchen ein Team.');
        abort_if($data['visibility'] === 'organization' && empty($data['club_id']), 422, 'Organization Events brauchen eine Organization.');
        abort_if(! empty($data['uses_penalty_catalog']) && empty($data['team_id']), 422, 'Der Strafkatalog ist nur für Team-Events verfügbar.');

        $data['uses_penalty_catalog'] = (bool) ($data['uses_penalty_catalog'] ?? false);

        return $data;
    }

    private function canManageTeamCashbox(User $user, Team $team): bool
    {
        return $user->can('update', $team)
            || $team->users()
                ->where('users.id', $user->id)
                ->wherePivotIn('role', \App\Support\TeamRoles::TEAM_STAFF_ROLES)
                ->exists();
    }

    private function eventDefaultFiltersFor(User $user): array
    {
        return $this->normalizeEventFilters(array_merge([
            'period' => 'upcoming',
            'radius_km' => $user->event_radius_km,
            'sport_ids' => $user->event_default_sport_ids ?? [],
        ], $user->event_default_filters ?? []));
    }

    private function eventCreationLimitsFor(User $user): array
    {
        $isLimited = ! $this->hasUnlimitedEventCreation($user);
        $used = $this->eventsCreatedThisMonth($user);
        $limit = 2;

        return [
            'is_free_limited' => $isLimited,
            'monthly_limit' => $isLimited ? $limit : null,
            'used_this_month' => $isLimited ? $used : null,
            'remaining_this_month' => $isLimited ? max(0, $limit - $used) : null,
            'allows_recurring' => ! $isLimited,
        ];
    }

    private function enforceEventCreationLimits(Request $request, array $data): void
    {
        if ($this->hasUnlimitedEventCreation($request->user())) {
            return;
        }

        if (filled($data['recurring'] ?? null)) {
            throw ValidationException::withMessages([
                'recurring' => 'Wiederholungen und Intervalle sind im kostenlosen Konto nicht verfügbar.',
                'authorization' => 'Kostenlose Konten können einfache Events erstellen, aber keine wiederkehrenden Events.',
            ]);
        }

        if ($this->eventsCreatedThisMonth($request->user()) >= 2) {
            throw ValidationException::withMessages([
                'authorization' => 'Im kostenlosen Konto kannst du 2 Events pro Monat erstellen. Dein Monatslimit ist erreicht.',
            ]);
        }
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

    private function estimatedRecurringEventCount(array $data): int
    {
        if (empty($data['recurring']) || empty($data['recurrence_ends_at'])) {
            return 1;
        }

        $timezone = $data['event_timezone'] ?? config('app.timezone', 'UTC');
        $start = CarbonImmutable::parse($data['start_time'], $timezone);
        $until = CarbonImmutable::parse($data['recurrence_ends_at'], $timezone)->endOfDay();

        return match ($data['recurring']) {
            'daily' => (int) $start->startOfDay()->diffInDays($until->startOfDay()) + 1,
            'weekly', 'biweekly' => $this->estimatedWeeklyRecurringEventCount(
                $start,
                $until,
                collect($data['recurrence_days'] ?? [])->map(fn ($day) => (int) $day),
                $data['recurring'] === 'biweekly' ? 2 : 1,
            ),
            'monthly' => $this->estimatedMonthlyRecurringEventCount($start, $until),
            default => 1,
        };
    }

    private function estimatedWeeklyRecurringEventCount(CarbonImmutable $start, CarbonImmutable $until, $recurrenceDays, int $interval): int
    {
        $count = 0;
        $cursor = $start->startOfDay();

        while ($cursor->lte($until) && $count <= self::MAX_RECURRING_EVENTS) {
            if ($recurrenceDays->contains($cursor->dayOfWeek)) {
                $weeksDiff = (int) $start->startOfWeek()->diffInWeeks($cursor->startOfWeek(), false);

                if ($weeksDiff >= 0 && $weeksDiff % $interval === 0) {
                    $count++;
                }
            }

            $cursor = $cursor->addDay();
        }

        return $count;
    }

    private function estimatedMonthlyRecurringEventCount(CarbonImmutable $start, CarbonImmutable $until): int
    {
        $count = 0;
        $months = 0;

        do {
            $candidate = $start->addMonthsNoOverflow($months);

            if ($candidate->lte($until)) {
                $count++;
            }

            $months++;
        } while ($candidate->lte($until) && $count <= self::MAX_RECURRING_EVENTS);

        return $count;
    }

    private function eventsCreatedThisMonth(User $user): int
    {
        return Event::query()
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();
    }

    private function normalizeEventFilters(array $filters): array
    {
        $sportIds = collect($filters['sport_ids'] ?? [])
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return [
            'search' => trim((string) ($filters['search'] ?? '')),
            'type' => $filters['type'] ?? '',
            'visibility' => $filters['visibility'] ?? '',
            'club_id' => $filters['club_id'] ?? '',
            'team_id' => $filters['team_id'] ?? '',
            'period' => $filters['period'] ?? 'upcoming',
            'radius_km' => filled($filters['radius_km'] ?? null) ? (int) $filters['radius_km'] : null,
            'sport_ids' => $sportIds,
        ];
    }

    private function applyEventSportFilter($query, array $sportIds): void
    {
        if (empty($sportIds)) {
            return;
        }

        $sportSlugs = Sport::query()
            ->whereIn('id', $sportIds)
            ->pluck('slug')
            ->filter()
            ->values()
            ->all();

        if (empty($sportSlugs)) {
            return;
        }

        $query->where(function ($query) use ($sportSlugs) {
            $query
                ->whereHas('team', fn ($teamQuery) => $teamQuery->whereIn('sport_type', $sportSlugs))
                ->orWhereHas('club', fn ($clubQuery) => $clubQuery->whereIn('sport_type', $sportSlugs))
                ->orWhereHas('team.club', fn ($clubQuery) => $clubQuery->whereIn('sport_type', $sportSlugs));
        });
    }

    private function applyEventLocationFilter($query, User $user, ?int $radiusKm): void
    {
        if (! $radiusKm) {
            return;
        }

        $postalPrefix = $this->postalPrefix($user->postal_code, $radiusKm);
        $city = trim((string) $user->city);
        $state = trim((string) $user->state);
        $country = strtoupper((string) $user->country);

        if (! $postalPrefix && ! $city && ! $state) {
            return;
        }

        $query->where(function ($query) use ($postalPrefix, $city, $state, $country) {
            $this->orWhereAddressMatches($query, 'club', $postalPrefix, $city, $state, $country);
            $this->orWhereAddressMatches($query, 'team.club', $postalPrefix, $city, $state, $country);

            if ($city !== '') {
                $query
                    ->orWhere('location_city', 'like', '%'.$city.'%')
                    ->orWhere('location', 'like', '%'.$city.'%');
            }

            if ($postalPrefix !== '') {
                $query
                    ->orWhere('location_postal_code', 'like', $postalPrefix.'%')
                    ->orWhere('location', 'like', $postalPrefix.'%');
            }
        });
    }

    private function normalizeStructuredLocation(array $data): array
    {
        $country = strtoupper(trim((string) ($data['location_country'] ?? '')));
        $data['location_country'] = $country ?: null;

        foreach (['location_name', 'location_street', 'location_house_number', 'location_postal_code', 'location_city'] as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            $data[$field] = $value !== '' ? $value : null;
        }

        $lineOne = trim(collect([
            $data['location_street'] ?? null,
            $data['location_house_number'] ?? null,
        ])->filter()->join(' '));
        $lineTwo = trim(collect([
            $data['location_postal_code'] ?? null,
            $data['location_city'] ?? null,
        ])->filter()->join(' '));

        $location = collect([
            $data['location_name'] ?? null,
            $lineOne !== '' ? $lineOne : null,
            $lineTwo !== '' ? $lineTwo : null,
        ])->filter()->join(', ');

        if ($location !== '') {
            $data['location'] = $location;
        } elseif (! empty($data['location'])) {
            $data['location'] = trim((string) $data['location']);
        } else {
            $data['location'] = null;
        }

        return $data;
    }

    private function orWhereAddressMatches($query, string $relationship, ?string $postalPrefix, string $city, string $state, string $country): void
    {
        $query->orWhereHas($relationship, function ($addressQuery) use ($postalPrefix, $city, $state, $country) {
            if ($country !== '') {
                $addressQuery->where('country', $country);
            }

            $addressQuery->where(function ($nearQuery) use ($postalPrefix, $city, $state) {
                if ($postalPrefix !== '') {
                    $nearQuery->orWhere('postal_code', 'like', $postalPrefix.'%');
                }

                if ($city !== '') {
                    $nearQuery->orWhere('city', 'like', '%'.$city.'%');
                }

                if ($state !== '') {
                    $nearQuery->orWhere('state', 'like', '%'.$state.'%');
                }
            });
        });
    }

    private function postalPrefix(?string $postalCode, int $radiusKm): string
    {
        $digits = preg_replace('/\D+/', '', (string) $postalCode);

        if ($digits === '') {
            return '';
        }

        $length = $radiusKm <= 20 ? 2 : ($radiusKm <= 50 ? 1 : 0);

        return $length > 0 ? substr($digits, 0, $length) : '';
    }

    private function sportsForFilters()
    {
        return Sport::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'category']);
    }

    private function notifyEventCommentRecipients(Event $event, $comment, User $actor): void
    {
        $event->loadMissing(['team.users:id', 'club.users:id', 'team.club.users:id', 'participants:id']);

        $recipientIds = collect([$event->user_id])
            ->merge($event->participants->pluck('id'));

        if ($event->team_id && $event->team) {
            $recipientIds = $recipientIds->merge($event->team->users->pluck('id'));
        } elseif ($event->club_id && $event->club) {
            $recipientIds = $recipientIds->merge($event->club->users->pluck('id'));
        } elseif ($event->team?->club) {
            $recipientIds = $recipientIds->merge($event->team->club->users->pluck('id'));
        }

        $recipientIds
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn ($id) => $id === (int) $actor->id)
            ->each(fn ($recipientId) => AppNotification::send($recipientId, 'event.comment', [
                'title' => $actor->name.' hat ein Event kommentiert',
                'body' => str($comment->content)->limit(120)->toString(),
                'url' => route('auth.events.show', $event->id),
                'actor_id' => $actor->id,
                'actor_name' => $actor->name,
                'event_id' => $event->id,
                'event_title' => $event->title,
                'comment_id' => $comment->id,
                'team_id' => $event->team_id,
                'club_id' => $event->club_id ?: $event->team?->club_id,
            ]));
    }

    private function notifyEventCancellationRecipients(Event $event, string $action, ?string $reason = null): void
    {
        $event->loadMissing(['participants' => fn ($query) => $query->select('users.id', 'name')]);

        $recipientIds = $event->participants
            ->filter(fn ($participant) => $participant->pivot?->status === 'yes')
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn ($id) => $id === (int) auth()->id());

        $title = $action === 'deleted'
            ? 'Event wurde gelöscht'
            : 'Event wurde abgesagt';

        $body = $reason
            ? $event->title.' wurde abgesagt. Grund: '.$reason
            : $event->title.' wurde abgesagt oder entfernt.';

        $recipientIds->each(fn ($recipientId) => AppNotification::send($recipientId, 'event.cancelled', [
            'title' => $title,
            'body' => str($body)->limit(180)->toString(),
            'url' => $action === 'deleted' ? route('auth.events.index') : route('auth.events.show', $event->id),
            'event_id' => $event->id,
            'event_title' => $event->title,
            'action' => $action,
            'reason' => $reason,
            'team_id' => $event->team_id,
            'club_id' => $event->club_id ?: $event->team?->club_id,
        ]));
    }

    private function grantGamificationForEvent(Request $request, Event $event): void
    {
        $actor = $request->user();
        $event->loadMissing(['club', 'team.club']);
        $reason = $event->type === 'training' ? 'training_created' : 'event_created';

        if ($event->club) {
            $this->gamification->grantToClub($actor, $event->club, $reason, $event, [
                'event_id' => $event->id,
                'event_type' => $event->type,
            ]);
        }

        if ($event->team) {
            $this->gamification->grantToTeam($actor, $event->team, $reason, $event, [
                'event_id' => $event->id,
                'event_type' => $event->type,
            ]);
        }

        if ($actor->hasAnyRole(['coach', 'assistant_coach', 'performance_coach', 'fitness_coach'])) {
            $this->gamification->grantToTrainer($actor, 'training_plan_created', $event, [
                'event_id' => $event->id,
                'event_type' => $event->type,
            ]);
        }
    }
}
