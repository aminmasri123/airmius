<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EventResource;
use App\Models\Club;
use App\Models\Event;
use App\Models\EventAttendanceCorrection;
use App\Models\EventCheckInToken;
use App\Models\EventParticipant;
use App\Models\Sport;
use App\Models\Team;
use App\Models\User;
use App\Services\CapacityBookingRuleService;
use App\Services\EventNotificationService;
use App\Services\EventParticipationLifecycleService;
use App\Services\EventService;
use App\Services\Training\TrainingRouteLinkService;
use App\Support\Api\V1\ApiPagination;
use App\Support\AppNotification;
use App\Support\ClubAuditLog;
use App\Support\EventAttendance;
use App\Support\EventCreationPermissions;
use App\Support\EventFileContext;
use App\Support\MinorSafety;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EventController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private EventService $service,
        private EventNotificationService $eventNotifications,
        private EventParticipationLifecycleService $participationLifecycle,
        private TrainingRouteLinkService $routeLinks,
        private EventFileContext $eventFiles,
        private CapacityBookingRuleService $bookingRules,
    ) {}

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

        return response()->json(ApiPagination::payload($events, EventResource::collection($events->getCollection())->resolve($request), [], [
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
            'teams' => EventCreationPermissions::availableTeams($request->user())
                ->map->only(['id', 'club_id', 'name'])
                ->values(),
            'event_types' => Event::TYPES,
            'visibilities' => Event::VISIBILITIES,
            'participant_statuses' => Event::PARTICIPANT_STATUSES,
            'sports' => Sport::query()->select(['id', 'name', 'slug'])->orderBy('name')->get(),
            'sport_routes' => $this->routeLinks->selectableRoutes($request->user()),
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
        ]));
    }

    public function show(Request $request, Event $event)
    {
        $this->authorizeVisible($request, $event);
        $this->enforceMobileEventDeadlines($event);

        $event = $this->decorateEvents(Event::query()->whereKey($event->id), $request)->firstOrFail();
        $event->setAttribute('event_file_context', $this->eventFiles->forApi($event, $request->user()));

        return new EventResource($event);
    }

    public function comments(Request $request, Event $event)
    {
        $this->authorizeVisible($request, $event);

        $comments = $event->comments()
            ->with('user:id,name,profile_photo_path')
            ->latest()
            ->paginate($this->perPage($request));

        return response()->json(ApiPagination::payload(
            $comments,
            $comments->getCollection()->map(fn ($comment) => $this->commentData($comment, $request))->values()->all()
        ));
    }

    public function comment(Request $request, Event $event)
    {
        $this->authorizeVisible($request, $event);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:1500'],
        ]);

        $comment = $event->comments()->create([
            'user_id' => $request->user()->id,
            'content' => $data['content'],
        ]);
        $comment->load('user:id,name,profile_photo_path');

        $this->notifyEventCommentRecipients($event, $comment, $request->user());

        return response()->json([
            'data' => $this->commentData($comment, $request),
        ], 201);
    }

    public function store(Request $request)
    {
        $data = $this->validatedEventPayload($request);
        $data['status'] = 'scheduled';
        $data = $this->normalizeEventPayload($request, $data);
        [$club, $team] = EventCreationPermissions::context($data);
        $this->authorize('create', [Event::class, $club, $team]);

        if (! empty($data['recurring']) && ! EventCreationPermissions::hasUnlimitedAccess($request->user(), $club, $team)) {
            throw ValidationException::withMessages([
                'recurring' => __('server.events.recurring_forbidden'),
            ]);
        }

        if (
            in_array($data['recurring'] ?? null, ['weekly', 'biweekly'], true) &&
            empty($data['recurrence_days'])
        ) {
            throw ValidationException::withMessages([
                'recurrence_days' => __('server.events.recurrence_day_required'),
            ]);
        }

        $event = $this->service->create($data);

        return new EventResource(
            $this->decorateEvents(Event::query()->whereKey($event->id), $request)->firstOrFail()
        );
    }

    public function update(Request $request, Event $event)
    {
        $this->authorizeVisible($request, $event);
        $this->authorize('update', $event);

        $data = $this->normalizeEventPayload(
            $request,
            $this->validatedEventPayload($request, false),
            $event
        );

        $this->service->update($event, $data);

        return new EventResource(
            $this->decorateEvents(Event::query()->whereKey($event->id), $request)->firstOrFail()
        );
    }

    public function cancel(Request $request, Event $event)
    {
        $this->authorizeVisible($request, $event);
        $this->authorize('cancel', $event);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ]);

        $this->participationLifecycle->cancelEvent($event, $request->user(), $data['reason'] ?? null, $data['idempotency_key'] ?? null);

        return new EventResource(
            $this->decorateEvents(Event::query()->whereKey($event->id), $request)->firstOrFail()
        );
    }

    public function destroy(Request $request, Event $event)
    {
        $this->authorizeVisible($request, $event);
        $this->authorize('delete', $event);

        $id = $event->id;

        $this->service->delete($event);

        return response()->json([
            'data' => [
                'id' => $id,
                'deleted' => true,
            ],
        ]);
    }

    public function respond(Request $request, Event $event)
    {
        $this->authorizeVisible($request, $event);
        $this->authorize('join', $event);
        $this->enforceMobileEventDeadlines($event);

        $data = $request->validate([
            'status' => ['required', Rule::in(Event::PARTICIPANT_STATUSES)],
            'response_reason' => ['nullable', 'string', 'max:500'],
            'absence_reason' => ['nullable', 'string', 'max:1000'],
            'camp_group_key' => ['nullable', 'string', 'max:80'],
            'camp_privacy_notice_accepted' => ['nullable', 'boolean'],
            'camp_privacy_notice_version' => ['nullable', 'string', 'max:120'],
            'camp_travel_consent_accepted' => ['nullable', 'boolean'],
            'camp_emergency_contact' => ['nullable', 'array'],
            'camp_emergency_contact.name' => ['required_with:camp_emergency_contact', 'string', 'max:120'],
            'camp_emergency_contact.phone' => ['required_with:camp_emergency_contact', 'string', 'max:80'],
            'camp_emergency_contact.relationship' => ['nullable', 'string', 'max:80'],
            'camp_dietary_notes' => ['nullable', 'string', 'max:1000'],
            'requirements_accepted' => ['nullable', 'boolean'],
            'consent_accepted' => ['nullable', 'boolean'],
            'consent_version' => ['nullable', 'string', 'max:120'],
        ]);

        $participation = DB::transaction(function () use ($event, $request, $data) {
            $lockedEvent = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            $this->expireWaitlistOffers($lockedEvent);

            if ($lockedEvent->participant_response_deadline_at?->isPast()) {
                throw ValidationException::withMessages([
                    'status' => __('server.events.response_deadline_expired'),
                ]);
            }

            $isMember = $this->isEventMember($lockedEvent, $request->user());

            if (($lockedEvent->registration_audience ?: 'members_and_guests') === 'members_only' && ! $isMember) {
                throw ValidationException::withMessages([
                    'status' => __('server.events.members_only_registration'),
                ]);
            }

            if ($data['status'] === 'yes' && ! empty($lockedEvent->participation_requirements) && ! $request->boolean('requirements_accepted')) {
                throw ValidationException::withMessages([
                    'requirements_accepted' => __('server.events.requirements_acceptance_required'),
                ]);
            }

            if ($data['status'] === 'yes') {
                $this->validateCampParticipation($lockedEvent, $request, $data);
            }

            if ($data['status'] === 'yes' && $lockedEvent->participation_consent_required) {
                $expectedVersion = (string) ($lockedEvent->participation_consent_version ?: '');
                if (! $request->boolean('consent_accepted') || ($expectedVersion !== '' && ($data['consent_version'] ?? null) !== $expectedVersion)) {
                    throw ValidationException::withMessages([
                        'consent_accepted' => __('server.events.consent_acceptance_required'),
                    ]);
                }
            }

            $existing = EventParticipant::query()
                ->where('event_id', $lockedEvent->id)
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            [$status, $waitlistPosition, $promotedAt, $offerExpiresAt] = $this->bookingRules
                ->eventResponseStatus($lockedEvent, $request->user(), $existing, $data['status']);

            return EventParticipant::query()->updateOrCreate(
                [
                    'event_id' => $lockedEvent->id,
                    'user_id' => $request->user()->id,
                ],
                [
                    'status' => $status,
                    'rsvp_status' => in_array($status, Event::RSVP_STATUSES, true) ? $status : null,
                    'response_reason' => $data['response_reason'] ?? null,
                    'absence_reason' => $data['absence_reason'] ?? null,
                    'camp_group_key' => in_array($status, ['yes', 'waitlist'], true) ? ($data['camp_group_key'] ?? null) : null,
                    'camp_privacy_notice_accepted_at' => $request->boolean('camp_privacy_notice_accepted') ? now() : null,
                    'camp_travel_consent_accepted_at' => $request->boolean('camp_travel_consent_accepted') ? now() : null,
                    'camp_guardian_consent_verified_at' => ($lockedEvent->camp_guardian_consent_required || $lockedEvent->camp_travel_consent_required) && MinorSafety::isUnderConsentAge($request->user()) ? now() : null,
                    'camp_emergency_contact_snapshot' => $data['camp_emergency_contact'] ?? null,
                    'camp_dietary_notes' => $data['camp_dietary_notes'] ?? null,
                    'response_mode' => 'mobile',
                    'responded_at' => now(),
                    'waitlist_position' => $status === 'waitlist' ? $waitlistPosition : null,
                    'waitlist_promoted_at' => $promotedAt,
                    'waitlist_offer_expires_at' => $offerExpiresAt,
                ]
            );
        });

        if ($participation->wasRecentlyCreated || $participation->wasChanged(['status', 'response_reason'])) {
            $this->eventNotifications->notifyParticipationResponse(
                $event,
                $request->user(),
                $participation->status,
                $data['response_reason'] ?? null,
            );
        }

        return new EventResource($this->decorateEvents(Event::query()->whereKey($event->id), $request)->firstOrFail());
    }

    public function leave(Request $request, Event $event)
    {
        $this->authorizeVisible($request, $event);
        $this->authorize('join', $event);
        $this->enforceMobileEventDeadlines($event);

        $deleted = DB::transaction(function () use ($event, $request) {
            $lockedEvent = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            $this->expireWaitlistOffers($lockedEvent);

            if ($lockedEvent->participant_response_deadline_at?->isPast()) {
                throw ValidationException::withMessages([
                    'status' => __('server.events.response_deadline_expired'),
                ]);
            }

            $deleted = EventParticipant::query()
                ->where('event_id', $lockedEvent->id)
                ->where('user_id', $request->user()->id)
                ->delete();

            if ($deleted > 0) {
                $this->promoteNextWaitlistedParticipant($lockedEvent);
            }

            return $deleted;
        });

        if ($deleted > 0) {
            $this->eventNotifications->notifyParticipationWithdrawn($event, $request->user());
        }

        return new EventResource($this->decorateEvents(Event::query()->whereKey($event->id), $request)->firstOrFail());
    }

    public function attendance(Request $request, Event $event)
    {
        $this->authorizeVisible($request, $event);
        abort_unless(EventAttendance::canManage($request->user(), $event), 403);

        $allowedUserIds = EventAttendance::allowedUserIds($event);
        $statuses = EventParticipant::query()
            ->where('event_id', $event->id)
            ->whereIn('user_id', $allowedUserIds)
            ->get(['user_id', 'status', 'rsvp_status', 'attendance_status', 'response_reason', 'absence_reason', 'checked_in_at', 'check_in_method'])
            ->keyBy('user_id');

        $members = User::query()
            ->whereIn('id', $allowedUserIds)
            ->select(['id', 'name', 'profile_photo_path'])
            ->orderBy('name')
            ->get()
            ->map(function (User $user) use ($statuses) {
                $participation = $statuses->get($user->id);

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'profile_photo_url' => $user->profile_photo_url,
                    'status' => $participation?->status,
                    'rsvp_status' => $participation?->rsvp_status,
                    'attendance_status' => $participation?->attendance_status,
                    'response_reason' => $participation?->response_reason,
                    'absence_reason' => $participation?->absence_reason,
                    'checked_in_at' => $participation?->checked_in_at?->toJSON(),
                    'check_in_method' => $participation?->check_in_method,
                ];
            })
            ->values();

        return response()->json([
            'data' => $members,
            'meta' => [
                'event_id' => $event->id,
                'statuses' => Event::PARTICIPANT_STATUSES,
            ],
        ]);
    }

    public function recordAttendance(Request $request, Event $event)
    {
        $this->authorizeVisible($request, $event);
        abort_unless(EventAttendance::canManage($request->user(), $event), 403);

        $data = $request->validate([
            'attendance' => ['required', 'array', 'min:1', 'max:500'],
            'attendance.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'attendance.*.status' => ['nullable', Rule::in(Event::PARTICIPANT_STATUSES)],
            'attendance.*.rsvp_status' => ['nullable', Rule::in(Event::RSVP_STATUSES)],
            'attendance.*.attendance_status' => ['nullable', Rule::in(Event::ATTENDANCE_STATUSES)],
            'attendance.*.response_reason' => ['nullable', 'string', 'max:1000'],
            'attendance.*.absence_reason' => ['nullable', 'string', 'max:1000'],
            'reason_code' => ['nullable', 'string', 'max:60'],
        ]);

        EventAttendance::record($event, $data['attendance'], 'trainer', $request->user(), $data['reason_code'] ?? null);

        return new EventResource($this->decorateEvents(Event::query()->whereKey($event->id), $request)->firstOrFail());
    }

    public function attendanceCorrections(Request $request, Event $event)
    {
        $this->authorizeVisible($request, $event);
        abort_unless(EventAttendance::canManage($request->user(), $event), 403);

        $allowedUserIds = EventAttendance::allowedUserIds($event);

        return response()->json([
            'data' => EventAttendanceCorrection::query()
                ->where('event_id', $event->id)
                ->whereIn('user_id', $allowedUserIds)
                ->with('actor:id,name')
                ->latest('id')
                ->limit(200)
                ->get()
                ->map(fn (EventAttendanceCorrection $correction) => [
                    'id' => $correction->id,
                    'user_id' => $correction->user_id,
                    'actor' => $correction->actor ? [
                        'id' => $correction->actor->id,
                        'name' => $correction->actor->name,
                    ] : null,
                    'source' => $correction->source,
                    'before_state' => $correction->before_state,
                    'after_state' => $correction->after_state,
                    'changed_fields' => $correction->changed_fields,
                    'reason_code' => $correction->reason_code,
                    'contains_private_note' => $correction->contains_private_note,
                    'created_at' => $correction->created_at?->toJSON(),
                ]),
        ]);
    }

    public function issueCheckInToken(Request $request, Event $event)
    {
        $this->authorizeVisible($request, $event);
        abort_unless(EventAttendance::canManage($request->user(), $event), 403);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'device_id' => ['nullable', 'string', 'min:8', 'max:160'],
            'valid_from' => ['nullable', 'date'],
            'ttl_seconds' => ['nullable', 'integer', 'min:30', 'max:900'],
        ]);

        $userId = (int) $data['user_id'];
        abort_unless(EventAttendance::allowedUserIds($event)->contains($userId), 422, 'Check-in ist nur für Mitglieder dieses Teams oder Vereins möglich.');

        $validFrom = isset($data['valid_from']) ? CarbonImmutable::parse($data['valid_from']) : CarbonImmutable::now();
        abort_if($validFrom->greaterThan(CarbonImmutable::now()->addMinutes(15)), 422, 'Das Check-in-Zeitfenster darf höchstens 15 Minuten in der Zukunft beginnen.');

        $plain = bin2hex(random_bytes(32));
        $token = EventCheckInToken::query()->create([
            'event_id' => $event->id,
            'user_id' => $userId,
            'issued_by' => $request->user()->id,
            'token_hash' => hash('sha256', $plain),
            'device_hash' => $this->deviceHash($data['device_id'] ?? null),
            'valid_from' => $validFrom,
            'expires_at' => $validFrom->addSeconds((int) ($data['ttl_seconds'] ?? 180)),
        ]);

        if ($club = $event->resolvedClub()) {
            ClubAuditLog::record($club, $request->user(), 'club.event.check_in_token_issued', $event, [
                'event_id' => $event->id,
                'user_id' => $userId,
                'token_id' => $token->id,
                'device_bound' => (bool) $token->device_hash,
                'valid_from' => $token->valid_from->toJSON(),
                'expires_at' => $token->expires_at->toJSON(),
            ]);
        }

        return response()->json([
            'data' => [
                'token' => $plain,
                'token_id' => $token->id,
                'valid_from' => $token->valid_from->toJSON(),
                'expires_at' => $token->expires_at->toJSON(),
                'expires_in_seconds' => max(0, now()->diffInSeconds($token->expires_at, false)),
                'device_bound' => (bool) $token->device_hash,
            ],
        ], 201);
    }

    public function checkIn(Request $request, Event $event)
    {
        $this->authorizeVisible($request, $event);

        $data = $request->validate([
            'token' => ['required', 'string', 'min:24', 'max:160'],
            'device_id' => ['nullable', 'string', 'min:8', 'max:160'],
        ]);

        $token = DB::transaction(function () use ($event, $request, $data): EventCheckInToken {
            $token = EventCheckInToken::query()
                ->where('event_id', $event->id)
                ->where('token_hash', hash('sha256', $data['token']))
                ->lockForUpdate()
                ->first();

            abort_unless($token, 422, 'Der Check-in-Code ist ungültig oder abgelaufen.');

            $token->increment('attempt_count');
            abort_if($token->revoked_at || $token->used_at || $token->valid_from->isFuture() || $token->expires_at->isPast(), 422, 'Der Check-in-Code ist ungültig oder abgelaufen.');

            $deviceHash = $this->deviceHash($data['device_id'] ?? null);
            abort_if($token->device_hash && ! hash_equals($token->device_hash, (string) $deviceHash), 422, 'Der Check-in-Code ist an ein anderes Gerät gebunden.');

            $participant = EventParticipant::query()
                ->where('event_id', $event->id)
                ->where('user_id', $token->user_id)
                ->lockForUpdate()
                ->first();
            $before = $participant ? EventAttendance::state($participant) : null;
            $values = [
                'event_id' => $event->id,
                'user_id' => $token->user_id,
                'status' => 'yes',
                'rsvp_status' => 'yes',
                'attendance_status' => 'present',
                'response_mode' => 'qr_check_in',
                'responded_at' => now(),
                'checked_in_at' => now(),
                'check_in_method' => 'qr_token',
            ];

            if ($participant) {
                $participant->forceFill($values)->save();
            } else {
                $participant = EventParticipant::query()->create($values);
            }

            $token->forceFill([
                'used_at' => now(),
                'device_hash' => $token->device_hash ?: $deviceHash,
            ])->save();

            EventAttendance::recordCorrection($event, $participant, $before, $request->user(), 'qr_check_in');

            if ($club = $event->resolvedClub()) {
                ClubAuditLog::record($club, $request->user(), 'club.event.check_in_completed', $participant, [
                    'event_id' => $event->id,
                    'user_id' => $token->user_id,
                    'token_id' => $token->id,
                    'device_bound' => (bool) $token->device_hash,
                ]);
            }

            return $token->refresh();
        });

        return response()->json([
            'data' => [
                'checked_in' => true,
                'user_id' => $token->user_id,
                'checked_in_at' => $token->used_at?->toJSON(),
            ],
        ]);
    }

    private function deviceHash(?string $deviceId): ?string
    {
        $deviceId = trim((string) $deviceId);

        return $deviceId === '' ? null : hash('sha256', $deviceId);
    }

    private function validatedEventPayload(Request $request, bool $creating = true): array
    {
        $required = fn () => $creating ? ['required'] : ['sometimes'];
        $optional = fn () => $creating ? ['nullable'] : ['sometimes', 'nullable'];

        return $request->validate([
            'club_id' => [...$optional(), 'exists:clubs,id'],
            'team_id' => [...$optional(), 'exists:teams,id'],
            'sport_route_id' => [...$optional(), 'integer'],
            'title' => [...$required(), 'string', 'max:255'],
            'type' => [...$required(), Rule::in(Event::TYPES)],
            'visibility' => [...$required(), Rule::in(Event::VISIBILITIES)],
            'start_time' => [...$required(), 'date'],
            'end_time' => [...$optional(), 'date', 'after_or_equal:start_time'],
            'location' => [...$optional(), 'string', 'max:255'],
            'location_name' => [...$optional(), 'string', 'max:255'],
            'location_street' => [...$optional(), 'string', 'max:255'],
            'location_house_number' => [...$optional(), 'string', 'max:40'],
            'location_postal_code' => [...$optional(), 'string', 'max:20'],
            'location_city' => [...$optional(), 'string', 'max:255'],
            'location_country' => [...$optional(), 'string', 'size:2'],
            'location_latitude' => [...$optional(), 'numeric', 'between:-90,90'],
            'location_longitude' => [...$optional(), 'numeric', 'between:-180,180'],
            'max_participants' => [...$optional(), 'integer', 'min:1', 'max:100000'],
            'registration_audience' => [...$optional(), Rule::in(Event::REGISTRATION_AUDIENCES)],
            'participation_requirements' => [...$optional(), 'array', 'max:20'],
            'participation_requirements.*' => ['string', 'max:255'],
            'camp_groups' => [...$optional(), 'array', 'max:30'],
            'camp_groups.*.key' => ['required_with:camp_groups', 'string', 'max:80', 'distinct'],
            'camp_groups.*.name' => ['required_with:camp_groups', 'string', 'max:120'],
            'camp_groups.*.capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'camp_groups.*.supervisor_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'camp_supervision' => [...$optional(), 'array', 'max:20'],
            'camp_accommodation' => [...$optional(), 'array', 'max:20'],
            'camp_catering' => [...$optional(), 'array', 'max:20'],
            'camp_emergency_contacts' => [...$optional(), 'array', 'max:20'],
            'camp_emergency_contacts.*.name' => ['required_with:camp_emergency_contacts', 'string', 'max:120'],
            'camp_emergency_contacts.*.phone' => ['required_with:camp_emergency_contacts', 'string', 'max:80'],
            'camp_guardian_consent_required' => [...$optional(), 'boolean'],
            'camp_travel_consent_required' => [...$optional(), 'boolean'],
            'camp_privacy_notice_version' => [...$optional(), 'string', 'max:120'],
            'participation_consent_required' => [...$optional(), 'boolean'],
            'participation_consent_version' => [...$optional(), 'string', 'max:120'],
            'member_price_cents' => [...$optional(), 'integer', 'min:0', 'max:100000000'],
            'guest_price_cents' => [...$optional(), 'integer', 'min:0', 'max:100000000'],
            'waitlist_offer_ttl_minutes' => [...$optional(), 'integer', 'min:1', 'max:10080'],
            'uses_penalty_catalog' => [...$optional(), 'boolean'],
            'notes' => [...$optional(), 'string'],
            'recurring' => [...$optional(), Rule::in(['daily', 'weekly', 'biweekly', 'monthly'])],
            'recurrence_days' => [...$optional(), 'array'],
            'recurrence_days.*' => ['integer', Rule::in([0, 1, 2, 3, 4, 5, 6])],
            'recurrence_ends_at' => [
                ...$optional(),
                'date',
                'after:start_time',
                ...($creating ? ['required_with:recurring'] : []),
            ],
            'reminder_at' => [...$optional(), 'date', 'before_or_equal:start_time'],
            'event_timezone' => [...$optional(), 'timezone'],
        ]);
    }

    private function normalizeEventPayload(Request $request, array $data, ?Event $event = null): array
    {
        $data['event_timezone'] ??= config('app.timezone', 'UTC');
        $data['registration_audience'] ??= $event?->registration_audience ?? 'members_and_guests';

        foreach (['member_price_cents', 'guest_price_cents'] as $priceField) {
            if (array_key_exists($priceField, $data)) {
                $data[$priceField] = (int) ($data[$priceField] ?? 0);
            }
        }

        $visibility = $data['visibility'] ?? $event?->visibility;

        if ($visibility === 'public') {
            $data['club_id'] = null;
            $data['team_id'] = null;
            $data['uses_penalty_catalog'] = false;
        }

        if ($visibility === 'organization') {
            $data['team_id'] = null;
            $data['uses_penalty_catalog'] = false;
        }

        if ($visibility === 'private') {
            $data['club_id'] = null;
        }

        if (array_key_exists('team_id', $data) && ! empty($data['team_id'])) {
            $team = Team::query()
                ->with('club')
                ->findOrFail($data['team_id']);

            abort_unless(
                $team->users()->where('users.id', $request->user()->id)->exists()
                    || EventCreationPermissions::hasScopedAccess($request->user(), $team->club, $team),
                404,
            );

            $data['club_id'] = $team->club_id;
        }

        if (array_key_exists('club_id', $data) && ! empty($data['club_id'])) {
            Club::query()
                ->whereHas('users', fn ($query) => $query->where('users.id', $request->user()->id))
                ->findOrFail($data['club_id']);
        }

        $finalTeamId = array_key_exists('team_id', $data) ? $data['team_id'] : $event?->team_id;
        $finalClubId = array_key_exists('club_id', $data) ? $data['club_id'] : $event?->club_id;
        $usesPenaltyCatalog = (bool) ($data['uses_penalty_catalog'] ?? $event?->uses_penalty_catalog ?? false);

        if ($visibility === 'private' && empty($finalTeamId)) {
            throw ValidationException::withMessages([
                'team_id' => __('server.events.private_team_required'),
            ]);
        }

        if ($visibility === 'organization' && empty($finalClubId)) {
            throw ValidationException::withMessages([
                'club_id' => __('server.events.organization_required'),
            ]);
        }

        if ($usesPenaltyCatalog && empty($finalTeamId)) {
            throw ValidationException::withMessages([
                'uses_penalty_catalog' => __('server.events.penalty_team_required'),
            ]);
        }

        $data['uses_penalty_catalog'] = $usesPenaltyCatalog;

        if (array_key_exists('sport_route_id', $data)) {
            $data['sport_route_id'] = $this->routeLinks
                ->resolveVisibleRoute($request->user(), $data['sport_route_id'], __('server.events.route_not_visible'))?->id;
        }

        return $data;
    }

    public function cancelParticipation(Request $request, Event $event)
    {
        $this->authorizeVisible($request, $event);
        $this->authorize('join', $event);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
            'refund' => ['nullable', 'boolean'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ]);

        $this->participationLifecycle->cancel($event, $request->user(), $data);

        return new EventResource($this->decorateEvents(Event::query()->whereKey($event->id), $request)->firstOrFail());
    }

    public function substituteParticipation(Request $request, Event $event)
    {
        $this->authorizeVisible($request, $event);
        $this->authorize('join', $event);

        $data = $request->validate([
            'replacement_user_id' => ['required', 'integer', 'exists:users,id'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ]);

        $replacement = User::query()->findOrFail($data['replacement_user_id']);
        $this->participationLifecycle->substitute($event, $request->user(), $replacement, $data);

        return new EventResource($this->decorateEvents(Event::query()->whereKey($event->id), $request)->firstOrFail());
    }

    private function visibleEvents(Request $request)
    {
        return Event::query()->visibleTo($request->user());
    }

    private function authorizeVisible(Request $request, Event $event): void
    {
        abort_unless(
            $this->visibleEvents($request)->whereKey($event->id)->exists()
                || $request->user()->can('view', $event),
            404,
        );
    }

    private function validateCampParticipation(Event $event, Request $request, array $data): void
    {
        $groups = collect($event->camp_groups ?? []);
        if ($groups->isNotEmpty()) {
            $key = $data['camp_group_key'] ?? null;
            $group = $groups->firstWhere('key', $key);

            if (! $group) {
                throw ValidationException::withMessages(['camp_group_key' => __('server.events.camp_group_required')]);
            }

            $capacity = (int) ($group['capacity'] ?? 0);
            if ($capacity > 0) {
                $booked = EventParticipant::query()
                    ->where('event_id', $event->id)
                    ->where('camp_group_key', $key)
                    ->whereIn('status', ['yes', 'waitlist'])
                    ->lockForUpdate()
                    ->count();

                if ($booked >= $capacity) {
                    throw ValidationException::withMessages(['camp_group_key' => __('server.events.camp_group_full')]);
                }
            }
        }

        if ($event->camp_privacy_notice_version) {
            $acceptedVersion = (string) ($data['camp_privacy_notice_version'] ?? '');
            if (! $request->boolean('camp_privacy_notice_accepted') || $acceptedVersion !== (string) $event->camp_privacy_notice_version) {
                throw ValidationException::withMessages(['camp_privacy_notice_accepted' => __('server.events.camp_privacy_notice_required')]);
            }
        }

        if ($event->camp_travel_consent_required && ! $request->boolean('camp_travel_consent_accepted')) {
            throw ValidationException::withMessages(['camp_travel_consent_accepted' => __('server.events.camp_travel_consent_required')]);
        }

        if (($event->camp_guardian_consent_required || $event->camp_travel_consent_required) && MinorSafety::isUnderConsentAge($request->user()) && ! MinorSafety::hasResolvedGuardianConsent($request->user())) {
            throw ValidationException::withMessages(['camp_guardian_consent_required' => __('server.events.camp_guardian_consent_required')]);
        }

        if (($event->camp_emergency_contacts || $event->camp_travel_consent_required) && empty($data['camp_emergency_contact'])) {
            throw ValidationException::withMessages(['camp_emergency_contact' => __('server.events.camp_emergency_contact_required')]);
        }
    }

    private function decorateEvents($query, Request $request)
    {
        $query->with('sportYearPeriod');
        $user = $request->user();

        return $query
            ->with([
                'club',
                'team',
                'user',
                'participants:id,name,email,profile_photo_path',
                'sportRoute' => fn ($routeQuery) => $routeQuery->select($this->routeLinks->routeColumns()),
            ])
            ->withCount([
                'participants',
                'comments',
                'participants as yes_count' => fn ($participants) => $participants->where('event_participants.status', 'yes'),
                'participants as late_count' => fn ($participants) => $participants->where('event_participants.status', 'late'),
                'participants as maybe_count' => fn ($participants) => $participants->where('event_participants.status', 'maybe'),
                'participants as no_count' => fn ($participants) => $participants->where('event_participants.status', 'no'),
                'participants as waitlist_count' => fn ($participants) => $participants->where('event_participants.status', 'waitlist'),
            ])
            ->addSelect([
                'my_participation_status' => EventParticipant::query()
                    ->select('status')
                    ->whereColumn('event_participants.event_id', 'events.id')
                    ->where('user_id', $user->id)
                    ->limit(1),
            ]);
    }

    private function isEventMember(Event $event, User $user): bool
    {
        $clubId = $event->club_id ?: $event->team?->club_id;

        if ($clubId && $user->clubs()->where('clubs.id', $clubId)->exists()) {
            return true;
        }

        return $event->team_id
            && $event->team?->users()->where('users.id', $user->id)->exists();
    }

    private function enforceMobileEventDeadlines(Event $event): void
    {
        DB::transaction(function () use ($event): void {
            $lockedEvent = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            $this->expireWaitlistOffers($lockedEvent);
        });
    }

    private function expireWaitlistOffers(Event $event): void
    {
        if (! $event->max_participants) {
            return;
        }

        $expiredOffers = EventParticipant::query()
            ->where('event_id', $event->id)
            ->where('status', 'yes')
            ->whereNotNull('waitlist_offer_expires_at')
            ->where('waitlist_offer_expires_at', '<', now())
            ->orderBy('waitlist_promoted_at')
            ->lockForUpdate()
            ->get();

        foreach ($expiredOffers as $expiredOffer) {
            $lastPosition = (int) EventParticipant::query()
                ->where('event_id', $event->id)
                ->where('status', 'waitlist')
                ->max('waitlist_position');

            $expiredOffer->forceFill([
                'status' => 'waitlist',
                'rsvp_status' => 'waitlist',
                'waitlist_position' => $lastPosition + 1,
                'waitlist_promoted_at' => null,
                'waitlist_offer_expires_at' => null,
            ])->save();

            $this->promoteNextWaitlistedParticipant($event);
        }
    }

    private function promoteNextWaitlistedParticipant(Event $event): ?EventParticipant
    {
        if (! $event->max_participants) {
            return null;
        }

        $yesCount = EventParticipant::query()
            ->where('event_id', $event->id)
            ->where('status', 'yes')
            ->count();

        if ($yesCount >= $event->max_participants) {
            return null;
        }

        $next = EventParticipant::query()
            ->where('event_id', $event->id)
            ->where('status', 'waitlist')
            ->orderBy('waitlist_position')
            ->orderBy('responded_at')
            ->lockForUpdate()
            ->first();

        if (! $next) {
            return null;
        }

        $next->forceFill([
            'status' => 'yes',
            'rsvp_status' => 'yes',
            'waitlist_promoted_at' => now(),
            'waitlist_offer_expires_at' => $event->waitlistOfferExpiresAt(),
        ])->save();

        return $next;
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->integer('per_page', 20), 1), 50);
    }

    private function commentData($comment, Request $request): array
    {
        return [
            'id' => $comment->id,
            'event_id' => $comment->event_id,
            'content' => $comment->content,
            'mine' => (int) $comment->user_id === (int) $request->user()->id,
            'user' => [
                'id' => $comment->user?->id,
                'name' => $comment->user?->name,
                'profile_photo_url' => $comment->user?->profile_photo_url,
            ],
            'created_at' => $comment->created_at?->toJSON(),
            'updated_at' => $comment->updated_at?->toJSON(),
        ];
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

    private function eventCreationLimitsFor(User $user): array
    {
        return EventCreationPermissions::capabilities($user);
    }
}
