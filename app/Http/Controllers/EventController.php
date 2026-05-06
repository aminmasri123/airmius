<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\Sport;
use App\Models\Team;
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
        ]);

        $filterKeys = ['search', 'type', 'visibility', 'club_id', 'team_id', 'period', 'radius_km', 'sport_ids'];
        $hasManualFilters = collect($filterKeys)->contains(fn ($key) => $request->query->has($key));
        $filters = $this->normalizeEventFilters($hasManualFilters
            ? $filters
            : array_merge($this->eventDefaultFiltersFor($request->user()), $filters));

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
            ->withCount(['comments', 'participants'])
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

        $events = $eventsQuery
            ->get()
            ->each(function (Event $event) use ($request) {
                $event->setAttribute('current_participant_status', $event->participants->first()?->pivot?->status);
                $event->setAttribute('can_update', $request->user()->can('update', $event));
                $event->setAttribute('can_delete', $request->user()->can('delete', $event));
                $event->setAttribute('can_cancel', $request->user()->can('cancel', $event));
            });

        return Inertia::render('Auth/Dashboard/Events/Index', [
            'events' => $events,
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
            'filters' => [
                'search' => $filters['search'] ?? '',
                'type' => $filters['type'] ?? '',
                'visibility' => $filters['visibility'] ?? '',
                'club_id' => $filters['club_id'] ?? '',
                'team_id' => $filters['team_id'] ?? '',
                'period' => $filters['period'] ?? 'upcoming',
                'radius_km' => $filters['radius_km'] ?? '',
                'sport_ids' => $filters['sport_ids'] ?? [],
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

    public function show(Event $event)
    {
        $this->authorize('view', $event);

        $event->load([
            'user:id,name',
            'team:id,name,club_id',
            'club:id,name',
            'conversation:id',
            'cancelledBy:id,name',
            'participants:id,name',
            'comments' => fn ($query) => $query->with('user:id,name')->latest(),
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
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Event::class);

        $event = $this->service->create($this->validated($request));
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
            'notes' => ['nullable', 'string'],
            'recurring' => ['nullable', 'string', 'max:80'],
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

        if (! empty($data['recurrence_ends_at'])) {
            $startDate = CarbonImmutable::parse($data['start_time'])->startOfDay();
            $endDate = CarbonImmutable::parse($data['recurrence_ends_at'])->startOfDay();

            if ($endDate->lt($startDate)) {
                throw ValidationException::withMessages([
                    'recurrence_ends_at' => 'Das Wiederholungsende muss nach dem Startdatum liegen.',
                ]);
            }
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

        return $data;
    }

    private function eventDefaultFiltersFor(User $user): array
    {
        return $this->normalizeEventFilters(array_merge([
            'period' => 'upcoming',
            'radius_km' => $user->event_radius_km,
            'sport_ids' => $user->event_default_sport_ids ?? [],
        ], $user->event_default_filters ?? []));
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
                $query->orWhere('location', 'like', '%'.$city.'%');
            }

            if ($postalPrefix !== '') {
                $query->orWhere('location', 'like', $postalPrefix.'%');
            }
        });
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
            ? 'Event wurde geloescht'
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
