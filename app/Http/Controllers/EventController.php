<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\Team;
use App\Services\EventService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class EventController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private EventService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Event::class);

        $events = Event::query()
            ->with([
                'team:id,name,club_id',
                'club:id,name',
                'conversation:id',
                'participants' => fn ($query) => $query
                    ->where('users.id', $request->user()->id)
                    ->select('users.id', 'name'),
            ])
            ->withCount(['comments', 'participants'])
            ->where(function ($query) use ($request) {
                $query->where('visibility', 'public')
                    ->orWhereHas('team.users', fn ($q) => $q->where('users.id', $request->user()->id))
                    ->orWhereHas('club.users', fn ($q) => $q->where('users.id', $request->user()->id))
                    ->orWhereHas('team.club.users', fn ($q) => $q->where('users.id', $request->user()->id));
            })
            ->orderBy('start_time')
            ->get()
            ->each(fn (Event $event) => $event->setAttribute(
                'current_participant_status',
                $event->participants->first()?->pivot?->status,
            ));

        return Inertia::render('Auth/Dashboard/Events/Index', [
            'events' => $events,
            'clubs' => Club::query()
                ->visibleTo($request->user())
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get(),
            'teams' => Team::query()
                ->visibleTo($request->user())
                ->select(['id', 'club_id', 'name'])
                ->orderBy('name')
                ->get(),
            'eventTypes' => Event::TYPES,
            'visibilities' => Event::VISIBILITIES,
            'participantStatuses' => Event::PARTICIPANT_STATUSES,
        ]);
    }

    public function show(Event $event)
    {
        $this->authorize('view', $event);

        $event->load([
            'team:id,name,club_id',
            'club:id,name',
            'conversation:id',
            'participants:id,name',
            'comments' => fn ($query) => $query->with('user:id,name')->latest(),
        ]);

        return Inertia::render('Auth/Dashboard/Events/Show', [
            'event' => $event,
            'participantStatuses' => Event::PARTICIPANT_STATUSES,
            'currentParticipantStatus' => $event->participants()
                ->where('users.id', auth()->id())
                ->first()?->pivot?->status,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Event::class);

        $event = $this->service->create($this->validated($request));

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

        $this->service->delete($event);

        return redirect()->route('auth.events.index')->with('success', 'Event gelöscht.');
    }

    public function join(Request $request, Event $event)
    {
        $this->authorize('join', $event);

        $data = $request->validate([
            'status' => ['required', Rule::in(Event::PARTICIPANT_STATUSES)],
        ]);

        $event->participants()->syncWithoutDetaching([
            $request->user()->id => ['status' => $data['status']],
        ]);

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

        $event->comments()->create([
            'user_id' => $request->user()->id,
            'content' => $data['content'],
        ]);

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
            $team = Team::findOrFail($data['team_id']);
            $this->authorize('view', $team);
            $data['club_id'] = $team->club_id;
        }

        if (! empty($data['club_id'])) {
            $this->authorize('view', Club::findOrFail($data['club_id']));
        }

        abort_if($data['visibility'] === 'private' && empty($data['team_id']), 422, 'Private Events brauchen ein Team.');
        abort_if($data['visibility'] === 'organization' && empty($data['club_id']), 422, 'Organization Events brauchen eine Organization.');

        return $data;
    }
}
