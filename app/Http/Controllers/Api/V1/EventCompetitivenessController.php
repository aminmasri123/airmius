<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventDecision;
use App\Models\EventDecisionOption;
use App\Models\EventDecisionVote;
use App\Models\EventParticipant;
use App\Models\Ride;
use App\Models\Team;
use App\Models\TrainingBlock;
use App\Models\TrainingBlockItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EventCompetitivenessController extends Controller
{
    use AuthorizesRequests;

    public function participation(Request $request, Event $event)
    {
        $this->authorize('view', $event);

        $totals = EventParticipant::query()
            ->where('event_id', $event->id)
            ->select('status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $reasons = EventParticipant::query()
            ->where('event_id', $event->id)
            ->whereNotNull('response_reason')
            ->select('response_reason')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('response_reason')
            ->orderByDesc('count')
            ->get()
            ->mapWithKeys(fn ($entry) => [$entry->response_reason => (int) $entry->count])
            ->all();

        $current = $event->participants()
            ->where('users.id', $request->user()->id)
            ->first()?->pivot;

        return response()->json([
            'data' => [
                'event_id' => $event->id,
                'my_status' => $current?->status,
                'my_response' => [
                    'reason' => $current?->response_reason,
                    'responded_at' => $current?->responded_at?->toDateTimeString(),
                    'response_mode' => $current?->response_mode,
                ],
                'counts' => [
                    'yes' => (int) ($totals['yes'] ?? 0),
                    'late' => (int) ($totals['late'] ?? 0),
                    'maybe' => (int) ($totals['maybe'] ?? 0),
                    'no' => (int) ($totals['no'] ?? 0),
                    'total' => array_sum($totals),
                ],
                'responses' => $reasons,
                'status_options' => Event::PARTICIPANT_STATUSES,
                'response_required' => (bool) $event->participant_response_required,
                'response_deadline_at' => $event->participant_response_deadline_at?->toDateTimeString(),
            ],
        ]);
    }

    public function setParticipation(Request $request, Event $event)
    {
        $this->authorize('join', $event);

        if ($event->status === 'cancelled') {
            throw ValidationException::withMessages([
                'status' => 'Abgesagte Events können nicht beantwortet werden.',
            ]);
        }

        if ($this->isParticipationDeadlineExpired($event) && ! $request->user()->can('update', $event)) {
            throw ValidationException::withMessages([
                'status' => 'Die Rückmeldefrist für dieses Event ist bereits abgelaufen.',
            ]);
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(Event::PARTICIPANT_STATUSES)],
            'response_reason' => ['nullable', 'string', 'max:1000'],
            'response_mode' => ['nullable', Rule::in(['self', 'coach', 'manager', 'phone', 'other'])],
        ]);

        if (in_array($data['status'], ['no', 'late'], true)
            && ! trim((string) ($data['response_reason'] ?? ''))
        ) {
            throw ValidationException::withMessages([
                'response_reason' => 'Bitte eine Begründung angeben.',
            ]);
        }

        if ($data['status'] === 'yes' || $data['status'] === 'late') {
            $acceptedCount = EventParticipant::query()
                ->where('event_id', $event->id)
                ->whereIn('status', ['yes', 'late'])
                ->count();

            if ($event->max_participants && $acceptedCount >= $event->max_participants && ! $this->userAlreadyAccepted($event, $request->user()->id)) {
                throw ValidationException::withMessages([
                    'status' => 'Das Event ist bereits ausgebucht.',
                ]);
            }
        }

        $event->participants()->syncWithoutDetaching([
            $request->user()->id => [
                'status' => $data['status'],
                'response_reason' => $data['response_reason'] ?? null,
                'response_mode' => $data['response_mode'] ?? 'self',
                'responded_at' => now(),
            ],
        ]);

        $missingResponses = $this->countMissingParticipantResponses($event);
        $scopeCount = $this->participationScope($event)->count();
        $respondedCount = $event->participants()->count();

        return response()->json([
            'message' => 'Teilnahmeantwort gespeichert.',
            'data' => [
                'status' => $data['status'],
                'response_reason' => $data['response_reason'] ?? null,
                'response_deadline_at' => $event->participant_response_deadline_at?->toDateTimeString(),
                'missing_responses' => $missingResponses,
                'responded_count' => $respondedCount,
                'respondent_scope_count' => $scopeCount,
            ],
        ]);
    }

    public function setParticipationDeadline(Request $request, Event $event)
    {
        $this->authorize('update', $event);

        $data = $request->validate([
            'response_required' => ['required', 'boolean'],
            'participant_response_deadline_at' => ['nullable', 'date'],
            'reminder_at' => ['nullable', 'date'],
        ]);

        $deadlineAt = $data['participant_response_deadline_at'] ?? null;
        $reminderAt = $data['reminder_at'] ?? null;

        if ($data['response_required'] && is_null($deadlineAt)) {
            throw ValidationException::withMessages([
                'participant_response_deadline_at' => 'Bei aktivierter Pflichtantwort ist eine Deadline erforderlich.',
            ]);
        }

        if (! is_null($deadlineAt) && Carbon::parse($deadlineAt)->lt(now())) {
            throw ValidationException::withMessages([
                'participant_response_deadline_at' => 'Die Deadline darf nicht in der Vergangenheit liegen.',
            ]);
        }

        $event->update([
            'participant_response_required' => $data['response_required'],
            'participant_response_deadline_at' => $deadlineAt ? Carbon::parse($deadlineAt) : null,
            'reminder_at' => $reminderAt ? Carbon::parse($reminderAt) : null,
        ]);

        return response()->json([
            'message' => 'Teilnahmerichtlinie gespeichert.',
            'data' => [
                'response_required' => (bool) $event->participant_response_required,
                'participant_response_deadline_at' => $event->participant_response_deadline_at?->toDateTimeString(),
                'reminder_at' => $event->reminder_at?->toDateTimeString(),
            ],
        ]);
    }

    public function sendParticipationReminder(Request $request, Event $event)
    {
        $this->authorize('update', $event);

        $request->validate([
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $event->update([
            'reminder_sent_at' => now(),
        ]);

        return response()->json([
            'message' => 'Erinnerung ausgelöst.',
            'data' => [
                'event_id' => $event->id,
                'missing_responses' => $this->countMissingParticipantResponses($event),
                'reminder_sent_at' => $event->reminder_sent_at?->toDateTimeString(),
            ],
        ]);
    }

    public function removeParticipation(Request $request, Event $event)
    {
        $this->authorize('join', $event);
        $event->participants()->detach($request->user()->id);

        return response()->json([
            'message' => 'Teilnahmemeldung entfernt.',
        ]);
    }

    public function exportIcs(Event $event)
    {
        $payload = $this->formatIcs($event);

        return response($payload, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="event-'.$event->id.'.ics"',
        ]);
    }

    public function exportCsv(Request $request)
    {
        $events = $this->visibleEvents($request)
            ->select([
                'id',
                'club_id',
                'team_id',
                'title',
                'type',
                'visibility',
                'status',
                'start_time',
                'end_time',
                'location_name',
                'location',
                'location_city',
                'notes',
            ])
            ->orderBy('start_time')
            ->get();

        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, [
            'id', 'title', 'type', 'visibility', 'status', 'start_time', 'end_time',
            'location_name', 'location', 'location_city', 'notes', 'team_id', 'club_id',
        ], ',');

        foreach ($events as $event) {
            fputcsv($csv, [
                $event->id,
                $event->title,
                $event->type,
                $event->visibility,
                $event->status,
                optional($event->start_time)->toDateTimeString(),
                optional($event->end_time)->toDateTimeString(),
                $event->location_name,
                $event->location,
                $event->location_city,
                strip_tags((string) $event->notes),
                $event->team_id,
                $event->club_id,
            ], ',');
        }

        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="events.csv"',
        ]);
    }

    public function importCsv(Request $request)
    {
        $this->authorize('create', Event::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'club_id' => ['nullable', 'integer', 'exists:clubs,id'],
            'visibility' => ['nullable', Rule::in(Event::VISIBILITIES)],
        ]);

        $path = $request->file('file')->getRealPath();
        $rows = array_map('str_getcsv', file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        $header = array_map('trim', array_shift($rows));

        $created = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $line = array_map('trim', $row);
            $rowData = array_combine($header, $line);

            if (! is_array($rowData)) {
                $errors[] = "Zeile ".($index + 2)." ist ungültig.";
                continue;
            }

            try {
                $this->importedEventFromRow($request, $rowData);
                $created++;
            } catch (\Throwable $exception) {
                $errors[] = "Zeile ".($index + 2).": ".$exception->getMessage();
                Log::warning('CSV event import failed', ['line' => $index + 2, 'exception' => $exception::class]);
            }
        }

        return response()->json([
            'message' => "{$created} Events importiert.",
            'errors' => $errors,
        ]);
    }

    public function importIcs(Request $request)
    {
        $this->authorize('create', Event::class);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:ics,ical,txt'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'club_id' => ['nullable', 'integer', 'exists:clubs,id'],
            'visibility' => ['nullable', Rule::in(Event::VISIBILITIES)],
        ]);

        $teamId = (int) ($data['team_id'] ?? 0);
        $clubId = (int) ($data['club_id'] ?? 0);
        $visibility = $data['visibility'] ?? 'private';

        if (! $teamId && ! $clubId) {
            $visibility = 'public';
        }

        $content = file_get_contents($request->file('file')->getRealPath());
        $events = $this->parseIcsEvents($content);
        $created = 0;

        foreach ($events as $eventData) {
            $payload = array_merge($eventData, [
                'team_id' => $teamId ?: null,
                'club_id' => $clubId ?: null,
                'visibility' => $visibility,
                'user_id' => $request->user()->id,
            ]);

            Event::create($payload);
            $created++;
        }

        return response()->json([
            'message' => "{$created} Events importiert.",
        ]);
    }

    public function trainingBlocks(Request $request, Event $event)
    {
        $this->authorize('view', $event);

        $blocks = $event->trainingBlocks()->with('items')->get()->values();

        return response()->json([
            'data' => $blocks->map(function (TrainingBlock $block) {
                return [
                    'id' => $block->id,
                    'title' => $block->title,
                    'kind' => $block->kind,
                    'description' => $block->description,
                    'sort_order' => (int) $block->sort_order,
                    'items' => $block->items->map(fn (TrainingBlockItem $item) => [
                        'id' => $item->id,
                        'title' => $item->title,
                        'notes' => $item->notes,
                        'sort_order' => (int) $item->sort_order,
                        'meta' => $item->meta,
                    ]),
                ];
            }),
        ]);
    }

    public function storeTrainingBlock(Request $request, Event $event)
    {
        $this->authorize('update', $event);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'kind' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $block = TrainingBlock::create([
            ...$data,
            'team_id' => $event->team_id,
            'event_id' => $event->id,
            'kind' => $data['kind'] ?? 'formation',
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return response()->json([
            'message' => 'Aufstellung/Trainingsblock erstellt.',
            'data' => $this->trainingBlockPayload($block->load('items')),
        ]);
    }

    public function updateTrainingBlock(Request $request, Event $event, TrainingBlock $block)
    {
        $this->authorize('update', $event);
        abort_unless((int) $block->event_id === (int) $event->id, 404);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'kind' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $block->update(array_filter($data, fn ($value) => ! is_null($value)));

        return response()->json([
            'message' => 'Block aktualisiert.',
            'data' => $this->trainingBlockPayload($block->refresh()->load('items')),
        ]);
    }

    public function destroyTrainingBlock(Event $event, TrainingBlock $block)
    {
        $this->authorize('update', $event);
        abort_unless((int) $block->event_id === (int) $event->id, 404);
        $block->delete();

        return response()->json([
            'message' => 'Block gelöscht.',
        ]);
    }

    public function storeTrainingBlockItem(Request $request, Event $event, TrainingBlock $block)
    {
        $this->authorize('update', $event);
        abort_unless((int) $block->event_id === (int) $event->id, 404);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'meta' => ['nullable', 'array'],
        ]);

        $item = TrainingBlockItem::create([
            ...$data,
            'training_block_id' => $block->id,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return response()->json([
            'message' => 'Station erstellt.',
            'data' => [
                'id' => $item->id,
                'title' => $item->title,
                'notes' => $item->notes,
                'sort_order' => (int) $item->sort_order,
                'meta' => $item->meta,
            ],
        ]);
    }

    public function updateTrainingBlockItem(Request $request, Event $event, TrainingBlock $block, TrainingBlockItem $item)
    {
        $this->authorize('update', $event);
        abort_unless((int) $item->training_block_id === (int) $block->id, 404);
        abort_unless((int) $block->event_id === (int) $event->id, 404);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'meta' => ['nullable', 'array'],
        ]);

        $item->update(array_filter($data, fn ($value) => ! is_null($value)));

        return response()->json([
            'message' => 'Station aktualisiert.',
            'data' => [
                'id' => $item->id,
                'title' => $item->title,
                'notes' => $item->notes,
                'sort_order' => (int) $item->sort_order,
                'meta' => $item->meta,
            ],
        ]);
    }

    public function reorderTrainingBlocks(Request $request, Event $event)
    {
        $this->authorize('update', $event);

        $data = $request->validate([
            'blocks' => ['required', 'array', 'min:1'],
            'blocks.*.id' => ['required', 'integer', 'exists:training_blocks,id'],
            'blocks.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($data['blocks'] as $blockData) {
            TrainingBlock::query()
                ->where('id', $blockData['id'])
                ->where('event_id', $event->id)
                ->update(['sort_order' => $blockData['sort_order']]);
        }

        return response()->json([
            'message' => 'Block-Reihenfolge gespeichert.',
            'data' => [
                'event_id' => $event->id,
            ],
        ]);
    }

    public function reorderTrainingBlockItems(Request $request, Event $event, TrainingBlock $block)
    {
        $this->authorize('update', $event);
        abort_unless((int) $block->event_id === (int) $event->id, 404);

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:training_block_items,id'],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($data['items'] as $itemData) {
            TrainingBlockItem::query()
                ->where('id', $itemData['id'])
                ->where('training_block_id', $block->id)
                ->update(['sort_order' => $itemData['sort_order']]);
        }

        return response()->json([
            'message' => 'Stationsreihenfolge gespeichert.',
            'data' => [
                'event_id' => $event->id,
                'block_id' => $block->id,
            ],
        ]);
    }

    public function destroyTrainingBlockItem(Event $event, TrainingBlock $block, TrainingBlockItem $item)
    {
        $this->authorize('update', $event);
        abort_unless((int) $item->training_block_id === (int) $block->id, 404);
        abort_unless((int) $block->event_id === (int) $event->id, 404);
        $item->delete();

        return response()->json([
            'message' => 'Station gelöscht.',
        ]);
    }

    public function decisions(Request $request, Event $event)
    {
        $this->authorize('view', $event);

        $decisions = EventDecision::query()
            ->where('event_id', $event->id)
            ->with([
                'options',
                'votes' => fn ($query) => $query->where('user_id', $request->user()->id),
            ])
            ->withCount('votes')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $decisions->map(fn (EventDecision $decision) => [
                'id' => $decision->id,
                'question' => $decision->question,
                'description' => $decision->description,
                'status' => $decision->status,
                'closes_at' => $decision->closes_at?->toDateTimeString(),
                'my_option_id' => $decision->votes->first()?->event_decision_option_id,
                'options' => $decision->options->map(fn (EventDecisionOption $option) => [
                    'id' => $option->id,
                    'label' => $option->label,
                    'sort_order' => (int) $option->sort_order,
                    'votes' => $option->votes()->count(),
                ]),
                'votes' => $decision->votes_count,
            ]),
        ]);
    }

    public function createDecision(Request $request, Event $event)
    {
        $this->authorize('update', $event);

        $data = $request->validate([
            'question' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'closes_at' => ['nullable', 'date'],
            'options' => ['required', 'array', 'min:2'],
            'options.*' => ['required', 'string', 'max:255'],
        ]);

        $decision = DB::transaction(function () use ($request, $event, $data) {
            $decision = EventDecision::create([
                'event_id' => $event->id,
                'user_id' => $request->user()->id,
                'question' => $data['question'],
                'description' => $data['description'] ?? null,
                'closes_at' => $data['closes_at'] ?? null,
            ]);

            foreach ($data['options'] as $index => $label) {
                $decision->options()->create([
                    'label' => $label,
                    'sort_order' => (int) $index,
                ]);
            }

            return $decision->load('options');
        });

        return response()->json([
            'message' => 'Abstimmung erstellt.',
            'data' => [
                'id' => $decision->id,
                'question' => $decision->question,
                'status' => $decision->status,
                'options' => $decision->options->map(fn ($option) => [
                    'id' => $option->id,
                    'label' => $option->label,
                    'sort_order' => (int) $option->sort_order,
                ]),
            ],
        ], 201);
    }

    public function castVote(Request $request, Event $event, EventDecision $decision)
    {
        abort_unless((int) $decision->event_id === (int) $event->id, 404);
        $this->authorize('view', $event);

        if (! $decision->isOpen()) {
            throw ValidationException::withMessages([
                'decision' => 'Diese Abstimmung ist geschlossen.',
            ]);
        }

        $data = $request->validate([
            'option_id' => ['required', 'integer', 'exists:event_decision_options,id'],
        ]);

        $option = EventDecisionOption::query()
            ->where('id', $data['option_id'])
            ->where('event_decision_id', $decision->id)
            ->firstOrFail();

        $vote = EventDecisionVote::updateOrCreate(
            [
                'event_decision_id' => $decision->id,
                'user_id' => $request->user()->id,
            ],
            [
                'event_decision_option_id' => $option->id,
            ]
        );

        return response()->json([
            'message' => 'Abstimmung gespeichert.',
            'data' => [
                'vote_id' => $vote->id,
                'decision_id' => $decision->id,
                'option_id' => $vote->event_decision_option_id,
            ],
        ]);
    }

    public function closeDecision(Event $event, EventDecision $decision)
    {
        abort_unless((int) $decision->event_id === (int) $event->id, 404);
        $this->authorize('update', $event);

        $decision->update(['status' => 'closed']);

        return response()->json([
            'message' => 'Abstimmung geschlossen.',
            'data' => [
                'id' => $decision->id,
                'status' => $decision->status,
            ],
        ]);
    }

    public function carpool(Event $event)
    {
        $this->authorize('view', $event);

        $rides = $event->rides()
            ->with([
                'driver:id,name',
                'users:id,name',
            ])
            ->withCount([
                'users as accepted_count' => fn ($query) => $query->wherePivot('status', Ride::MEMBER_STATUS_ACCEPTED),
                'users as requested_count' => fn ($query) => $query->wherePivot('status', Ride::MEMBER_STATUS_REQUESTED),
            ])
            ->orderBy('departure_time')
            ->get()
            ->map(fn (Ride $ride) => [
                'id' => $ride->id,
                'from' => $ride->from,
                'to' => $ride->to,
                'pickup_name' => $ride->pickup_name,
                'departure_time' => $ride->departure_time?->toDateTimeString(),
                'seats' => (int) $ride->seats,
                'accepted_count' => (int) $ride->accepted_count,
                'requested_count' => (int) $ride->requested_count,
                'available_seats' => max(0, (int) $ride->seats - (int) $ride->accepted_count),
                'is_full' => ((int) $ride->accepted_count >= (int) $ride->seats),
                'visibility' => $ride->visibility,
                'driver' => $ride->driver?->only(['id', 'name']),
                'participants' => $ride->users->map(fn ($member) => $member->only(['id', 'name'])),
                'participants_count' => $ride->users->count(),
            ]);

        return response()->json([
            'data' => $rides,
        ]);
    }

    public function createCarpool(Request $request, Event $event)
    {
        $this->authorize('join', $event);

        $data = $request->validate([
            'from' => ['required', 'string', 'max:255', 'different:to'],
            'to' => ['required', 'string', 'max:255', 'different:from'],
            'pickup_name' => ['nullable', 'string', 'max:255'],
            'pickup_street' => ['nullable', 'string', 'max:255'],
            'pickup_house_number' => ['nullable', 'string', 'max:40'],
            'pickup_postal_code' => ['nullable', 'string', 'max:30'],
            'pickup_city' => ['nullable', 'string', 'max:255'],
            'pickup_country' => ['nullable', 'string', 'size:2'],
            'pickup_note' => ['nullable', 'string', 'max:500'],
            'departure_time' => ['required', 'date', 'after_or_equal:'.now()->addMinutes(10)->toDateTimeString()],
            'seats' => ['required', 'integer', 'min:1', 'max:20'],
            'contact_details' => ['nullable', 'string', 'max:1000'],
            'visibility' => ['nullable', Rule::in(Ride::VISIBILITIES)],
        ]);

        $ride = Ride::create([
            'event_id' => $event->id,
            'club_id' => $event->team?->club_id ?: $event->club_id,
            'team_id' => $event->team_id,
            'driver_id' => $request->user()->id,
            'from' => $data['from'],
            'to' => $data['to'],
            'pickup_name' => $data['pickup_name'] ?? null,
            'pickup_street' => $data['pickup_street'] ?? null,
            'pickup_house_number' => $data['pickup_house_number'] ?? null,
            'pickup_postal_code' => $data['pickup_postal_code'] ?? null,
            'pickup_city' => $data['pickup_city'] ?? null,
            'pickup_country' => isset($data['pickup_country']) ? strtoupper($data['pickup_country']) : null,
            'pickup_note' => $data['pickup_note'] ?? null,
            'departure_time' => Carbon::parse($data['departure_time']),
            'seats' => $data['seats'],
            'contact_details' => $data['contact_details'] ?? null,
            'visibility' => $data['visibility'] ?? 'team',
        ]);

        $ride->users()->attach($request->user()->id, [
            'status' => Ride::MEMBER_STATUS_ACCEPTED,
            'message' => 'Organisator',
            'responded_at' => now(),
        ]);

        return response()->json([
            'message' => 'Fahrgemeinschaft angelegt.',
            'data' => [
                'id' => $ride->id,
                'event_id' => $event->id,
                'seats' => (int) $ride->seats,
                'accepted_count' => (int) $ride->users()->wherePivot('status', Ride::MEMBER_STATUS_ACCEPTED)->count(),
                'requested_count' => (int) $ride->users()->wherePivot('status', Ride::MEMBER_STATUS_REQUESTED)->count(),
                'available_seats' => max(0, (int) $ride->seats - (int) $ride->users()->wherePivot('status', Ride::MEMBER_STATUS_ACCEPTED)->count()),
            ],
        ]);
    }

    public function joinCarpool(Request $request, Event $event, Ride $ride)
    {
        $this->authorize('join', $event);
        abort_unless((int) $ride->event_id === (int) $event->id, 404);

        if ($this->isParticipationDeadlineExpired($event) && ! $request->user()->can('update', $event)) {
            throw ValidationException::withMessages([
                'ride' => 'Die Teilnahmefrist für dieses Event ist abgelaufen.',
            ]);
        }

        $data = $request->validate([
            'status' => ['nullable', Rule::in([Ride::MEMBER_STATUS_ACCEPTED, Ride::MEMBER_STATUS_REQUESTED])],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $status = $data['status'] ?? Ride::MEMBER_STATUS_ACCEPTED;
        $already = $this->rideHasUser($ride, $request->user()->id);
        $acceptedCount = $ride->users()->wherePivot('status', Ride::MEMBER_STATUS_ACCEPTED)->count();
        $isFull = $acceptedCount >= $ride->seats;

        if (! $already && $status === Ride::MEMBER_STATUS_ACCEPTED && $isFull) {
            $status = Ride::MEMBER_STATUS_REQUESTED;
        }

        $ride->users()->syncWithoutDetaching([
            $request->user()->id => [
                'status' => $status,
                'message' => $data['message'] ?? null,
                'responded_at' => now(),
            ],
        ]);

        return response()->json([
            'message' => 'Fahrgemeinschafts-Termin gespeichert.',
            'data' => [
                'ride_id' => $ride->id,
                'status' => $status,
                'is_pending' => $status === Ride::MEMBER_STATUS_REQUESTED,
                'available_seats' => max(0, (int) $ride->seats - (int) $ride->users()->wherePivot('status', Ride::MEMBER_STATUS_ACCEPTED)->count()),
            ],
        ]);
    }

    public function leaveCarpool(Request $request, Event $event, Ride $ride)
    {
        $this->authorize('join', $event);
        abort_unless((int) $ride->event_id === (int) $event->id, 404);

        if (! $this->rideHasUser($ride, $request->user()->id)) {
            return response()->json([
                'message' => 'Nicht eingetragen.',
            ]);
        }

        $ride->users()->detach($request->user()->id);

        return response()->json([
            'message' => 'Aus der Fahrgemeinschaft ausgetragen.',
        ]);
    }

    public function insights(Event $event)
    {
        $this->authorize('view', $event);

        $statusCounts = EventParticipant::query()
            ->where('event_id', $event->id)
            ->select('status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $reasons = EventParticipant::query()
            ->where('event_id', $event->id)
            ->whereNotNull('response_reason')
            ->select('response_reason')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('response_reason')
            ->orderByDesc('count')
            ->get()
            ->mapWithKeys(fn ($entry) => [$entry->response_reason => (int) $entry->count])
            ->all();

        $memberScope = $this->participationScope($event)->count();
        $participants = $event->participants()->withPivot('status', 'response_reason', 'response_mode')->get();
        $ageGroups = $this->ageGroupsFromParticipants($participants);
        $teamTrend = $event->team ? $this->teamParticipationTrend($event->team) : [];

        $responded = (int) array_sum($statusCounts);
        $pending = max(0, $memberScope - $responded);
        $yesLate = (int) (($statusCounts['yes'] ?? 0) + ($statusCounts['late'] ?? 0));
        $responseModes = EventParticipant::query()
            ->where('event_id', $event->id)
            ->whereNotNull('response_mode')
            ->select('response_mode')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('response_mode')
            ->orderByDesc('count')
            ->get()
            ->mapWithKeys(fn ($entry) => [$entry->response_mode => (int) $entry->count])
            ->all();

        return response()->json([
            'data' => [
                'event_id' => $event->id,
                'title' => $event->title,
                'participant_counts' => [
                    'yes' => (int) ($statusCounts['yes'] ?? 0),
                    'late' => (int) ($statusCounts['late'] ?? 0),
                    'maybe' => (int) ($statusCounts['maybe'] ?? 0),
                    'no' => (int) ($statusCounts['no'] ?? 0),
                    'responded' => $responded,
                    'pending' => $pending,
                ],
                'metrics' => [
                    'yes_late_ratio' => $responded === 0 ? 0 : round(($yesLate / $responded) * 100, 2),
                    'attendance_ratio' => $memberScope === 0 ? 0 : round(($yesLate / $memberScope) * 100, 2),
                    'response_rate' => $memberScope === 0 ? 0 : round(($responded / $memberScope) * 100, 2),
                    'missing_responses' => $this->countMissingParticipantResponses($event),
                ],
                'response_reasons' => $reasons,
                'response_modes' => $responseModes,
                'age_groups' => $ageGroups,
                'team_trend' => $teamTrend,
                'decision_count' => EventDecision::query()->where('event_id', $event->id)->count(),
                'training_block_count' => $event->trainingBlocks()->count(),
                'carpool_count' => $event->rides()->count(),
            ],
        ]);
    }

    public function trainerQuickActions(Request $request, Event $event)
    {
        $this->authorize('view', $event);

        $isEventOrganizer = (int) $event->user_id === (int) $request->user()->id;
        $isCoach = $event->team
            ? $event->team->users()->where('users.id', $request->user()->id)
                ->wherePivotIn('role', Team::TEAM_STAFF_ROLES)
                ->exists()
            : false;

        if (! $isCoach && ! $request->user()->can('update', $event)) {
            return response()->json([
                'data' => [
                    'actions' => [
                        ['type' => 'track_event', 'label' => 'Event im Kalender prüfen'],
                    ],
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'actions' => [
                    ['type' => 'invite_to_decision', 'label' => 'Schnell-Umfrage starten', 'enabled' => true],
                    ['type' => 'create_training_block', 'label' => 'Aufstellungsblock anlegen', 'enabled' => true],
                    ['type' => 'start_carpool', 'label' => 'Carpool anlegen', 'enabled' => true],
                    ['type' => 'set_deadline', 'label' => 'Teilnahme-Frist setzen', 'enabled' => true],
                    ['type' => 'send_reminder', 'label' => 'Erinnerung auslösen', 'enabled' => true],
                ],
                'is_event_organizer' => $isEventOrganizer || $isCoach,
            ],
        ]);
    }

    private function visibleEvents(Request $request)
    {
        $user = $request->user();
        $clubIds = $user->clubs()->pluck('clubs.id')->all();
        $teamIds = $user->teams()->pluck('teams.id')->all();

        return Event::query()->where(function ($query) use ($user, $clubIds, $teamIds) {
            $query->where('visibility', 'public')
                ->orWhere('user_id', $user->id)
                ->orWhereIn('club_id', $clubIds)
                ->orWhereIn('team_id', $teamIds)
                ->orWhereHas('participants', fn ($participants) => $participants->where('users.id', $user->id));
        });
    }

    private function formatIcs(Event $event): string
    {
        $rows = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Airmius//DE',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.$event->id.'@airmius.local',
            'DTSTAMP:'.now()->format('Ymd\THis\Z'),
            'DTSTART:'.$this->icalDate($event->start_time),
            $event->end_time ? 'DTEND:'.$this->icalDate($event->end_time) : '',
            'SUMMARY:'.str_replace(["\n", "\r", ','], ['\\n', '', '\,'], (string) $event->title),
            $event->location_name ? 'LOCATION:'.$this->icsEscape((string) $event->location_name) : '',
            $event->notes ? 'DESCRIPTION:'.$this->icsEscape((string) $event->notes) : '',
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", array_filter($rows));
    }

    private function icalDate(?\Illuminate\Support\Carbon $date): string
    {
        return $date ? $date->setTimezone('UTC')->format('Ymd\THis\Z') : now()->setTimezone('UTC')->format('Ymd\THis\Z');
    }

    private function icsEscape(string $value): string
    {
        return str_replace(
            ['\\', "\r", "\n", ';', ','],
            ['\\\\', '\r', '\n', '\;', '\,'],
            $value
        );
    }

    private function parseIcsEvents(string $content): array
    {
        $blocks = preg_split('/BEGIN:VEVENT|END:VEVENT/', $content);
        $events = [];

        foreach ($blocks as $block) {
            if (! str_contains($block, 'SUMMARY')) {
                continue;
            }

            $data = [];
            foreach (preg_split('/\r\n|\n|\r/', trim($block)) as $line) {
                if (str_starts_with($line, 'SUMMARY:')) {
                    $data['title'] = trim(substr($line, 8));
                }

                if (str_starts_with($line, 'DTSTART')) {
                    $value = trim(explode(':', $line, 2)[1] ?? '');
                    $data['start_time'] = $this->parseIcsDateTime($value);
                }

                if (str_starts_with($line, 'DTEND')) {
                    $value = trim(explode(':', $line, 2)[1] ?? '');
                    $data['end_time'] = $this->parseIcsDateTime($value);
                }

                if (str_starts_with($line, 'LOCATION:')) {
                    $data['location_name'] = trim(substr($line, 9));
                }

                if (str_starts_with($line, 'DESCRIPTION:')) {
                    $data['notes'] = trim(substr($line, 12));
                }
            }

            if (empty($data['title'])) {
                continue;
            }

            $events[] = [
                'title' => $data['title'] ?? 'Importiertes Event',
                'type' => 'training',
                'status' => 'scheduled',
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'] ?? null,
                'location_name' => $data['location_name'] ?? null,
                'notes' => $data['notes'] ?? null,
                'location' => $data['location_name'] ?? null,
            ];
        }

        return $events;
    }

    private function parseIcsDateTime(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return now()->toDateTimeString();
        }

        try {
            if (str_ends_with($value, 'Z')) {
                return Carbon::createFromFormat('Ymd\THis\Z', $value, 'UTC')->toDateTimeString();
            }

            if (strlen($value) === 8) {
                return Carbon::createFromFormat('Ymd', $value)->toDateTimeString();
            }

            return Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable $exception) {
            return now()->toDateTimeString();
        }
    }

    private function importedEventFromRow(Request $request, array $row): void
    {
        $title = $row['title'] ?? $row['name'] ?? $row['summary'] ?? null;
        if (! $title) {
            throw new \RuntimeException('Titel fehlt');
        }

        $start = $row['start_time'] ?? $row['start'] ?? null;
        $end = $row['end_time'] ?? $row['end'] ?? null;

        if (! $start || ! $end) {
            throw new \RuntimeException('Start-/Endzeit fehlt');
        }

        $teamId = (int) ($request->integer('team_id') ?? 0);
        $clubId = (int) ($request->integer('club_id') ?? 0);
        $visibility = $request->input('visibility', $teamId ? 'private' : 'public');

        if ($teamId) {
            $team = Team::query()->findOrFail($teamId);
            $clubId = (int) $team->club_id;
        }

        Event::create([
            'club_id' => $clubId ?: null,
            'team_id' => $teamId ?: null,
            'user_id' => $request->user()->id,
            'title' => $title,
            'type' => $row['type'] ?? 'training',
            'visibility' => $visibility,
            'start_time' => Carbon::parse($start),
            'end_time' => Carbon::parse($end),
            'location' => $row['location'] ?? $row['location_name'] ?? null,
            'location_name' => $row['location_name'] ?? $row['location'] ?? null,
            'notes' => $row['notes'] ?? $row['description'] ?? null,
            'status' => 'scheduled',
            'max_participants' => isset($row['max_participants']) ? (int) $row['max_participants'] : null,
            'team_id' => $teamId ?: null,
        ]);
    }

    private function trainingBlockPayload(TrainingBlock $block): array
    {
        return [
            'id' => $block->id,
            'title' => $block->title,
            'kind' => $block->kind,
            'description' => $block->description,
            'sort_order' => (int) $block->sort_order,
            'items' => $block->items->map(fn (TrainingBlockItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'notes' => $item->notes,
                'sort_order' => (int) $item->sort_order,
                'meta' => $item->meta,
            ]),
        ];
    }

    private function isParticipationDeadlineExpired(Event $event): bool
    {
        return (bool) $event->participant_response_deadline_at
            && now()->greaterThanOrEqualTo($event->participant_response_deadline_at);
    }

    private function participationScope(Event $event): \Illuminate\Support\Collection
    {
        if ($event->team_id) {
            return $event->team?->users()->pluck('users.id') ?? collect();
        }

        if ($event->club_id) {
            return $event->club?->users()->pluck('users.id') ?? collect();
        }

        return collect([$event->user_id]);
    }

    private function countMissingParticipantResponses(Event $event): int
    {
        $scopeCount = $this->participationScope($event)->count();
        $respondedCount = EventParticipant::query()
            ->where('event_id', $event->id)
            ->whereIn('user_id', $this->participationScope($event))
            ->count();

        return max(0, $scopeCount - $respondedCount);
    }

    private function rideHasUser(Ride $ride, int $userId): bool
    {
        return $ride->users()->wherePivot('user_id', $userId)->exists();
    }

    private function ageGroupFromBirthDate(?string $birthDate): string
    {
        if (! $birthDate) {
            return 'unbekannt';
        }

        try {
            $age = \Carbon\Carbon::parse($birthDate)->age;
        } catch (\Throwable $exception) {
            return 'unbekannt';
        }

        if ($age < 8) {
            return 'u8';
        }

        if ($age < 10) {
            return 'u10';
        }

        if ($age < 12) {
            return 'u12';
        }

        if ($age < 14) {
            return 'u14';
        }

        if ($age < 16) {
            return 'u16';
        }

        if ($age < 18) {
            return 'u18';
        }

        if ($age < 30) {
            return 'u30';
        }

        return 'u30+';
    }

    private function ageGroupsFromParticipants($participants): array
    {
        $groups = [];

        foreach ($participants as $participant) {
            $group = $this->ageGroupFromBirthDate((string) $participant->birth_date);
            $groups[$group] = ($groups[$group] ?? 0) + 1;
        }

        ksort($groups);

        return $groups;
    }

    private function teamParticipationTrend(Team $team, int $limit = 8): array
    {
        return Event::query()
            ->where('team_id', $team->id)
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('start_time')
            ->orderByDesc('start_time')
            ->limit($limit)
            ->get()
            ->map(fn (Event $event) => [
                'event_id' => $event->id,
                'date' => optional($event->start_time)?->toDateString(),
                'team_size' => (int) $this->participationScope($event)->count(),
                'responded' => (int) EventParticipant::query()
                    ->where('event_id', $event->id)
                    ->whereIn('user_id', $this->participationScope($event))
                    ->count(),
                'yes_late' => (int) EventParticipant::query()
                    ->where('event_id', $event->id)
                    ->whereIn('status', ['yes', 'late'])
                    ->count(),
                'no_show' => (int) EventParticipant::query()
                    ->where('event_id', $event->id)
                    ->whereIn('status', ['no'])
                    ->count(),
            ])
            ->values()
            ->toArray();
    }

    private function userAlreadyAccepted(int $eventId, int $userId): bool
    {
        return EventParticipant::query()
            ->where('event_id', $eventId)
            ->where('user_id', $userId)
            ->whereIn('status', ['yes', 'late'])
            ->exists();
    }
}
