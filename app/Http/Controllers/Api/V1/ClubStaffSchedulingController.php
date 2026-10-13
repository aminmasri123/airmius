<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubStaffAssignment;
use App\Models\ClubStaffAvailability;
use App\Models\ClubStaffChangeRequest;
use App\Models\Event;
use App\Models\Team;
use App\Models\User;
use App\Services\ClubStaffSchedulingService;
use App\Support\ClubPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClubStaffSchedulingController extends Controller
{
    public function __construct(private ClubStaffSchedulingService $scheduling) {}

    public function index(Request $request, Club $club)
    {
        $this->authorizeView($request, $club);
        $canManage = $this->canManage($request, $club);
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after:from'],
        ]);

        $from = isset($data['from']) ? $request->date('from') : now()->startOfMonth();
        $to = isset($data['to']) ? $request->date('to') : now()->endOfMonth();

        return response()->json(['data' => [
            ...$this->scheduling->calendarFor($club, $from, $to),
            'range' => [
                'from' => $from->toJSON(),
                'to' => $to->toJSON(),
            ],
            'can_manage' => $canManage,
            'current_user_id' => $request->user()?->id,
            'members' => $canManage
                ? $club->users()
                    ->wherePivot('membership_status', 'active')
                    ->orderBy('name')
                    ->get(['users.id', 'users.name'])
                    ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
                    ->values()
                : [],
            'change_requests' => ClubStaffChangeRequest::query()
                ->with(['requester:id,name', 'target:id,name'])
                ->where('club_id', $club->id)
                ->when(! $canManage, fn ($query) => $query->where(fn ($query) => $query
                    ->where('requested_by', $request->user()->id)
                    ->orWhere('target_user_id', $request->user()->id)))
                ->whereHas('assignment', fn ($query) => $query
                    ->where('starts_at', '<', $to)
                    ->where('ends_at', '>', $from))
                ->latest()
                ->get()
                ->map(fn (ClubStaffChangeRequest $changeRequest) => $this->changeRequestPayload($changeRequest))
                ->values(),
            'availability_statuses' => ClubStaffAvailability::STATUSES,
            'assignment_statuses' => ClubStaffAssignment::STATUSES,
        ]]);
    }

    public function storeAvailability(Request $request, Club $club)
    {
        $this->authorizeManage($request, $club);
        $data = $this->availabilityData($request, $club);

        $availability = ClubStaffAvailability::query()->create([
            ...$data,
            'club_id' => $club->id,
            'created_by' => $request->user()->id,
            'source' => $data['source'] ?? 'manual',
        ]);

        return response()->json(['data' => $this->scheduling->availabilityPayload($availability)], 201);
    }

    public function storeAssignment(Request $request, Club $club)
    {
        $this->authorizeManage($request, $club);
        $data = $this->assignmentData($request, $club);

        $conflicts = ! empty($data['user_id'])
            ? $this->scheduling->conflictsFor(
                $club,
                (int) $data['user_id'],
                $request->date('starts_at'),
                $request->date('ends_at'),
                null,
                $data['required_qualifications'] ?? [],
            )
            : [];

        if ($this->hasBlockingConflicts($conflicts) && ! $request->boolean('force')) {
            return response()->json([
                'message' => 'Assignment has blocking calendar, availability, or qualification conflicts.',
                'errors' => [
                    'conflicts' => ['Assignment has blocking calendar, availability, or qualification conflicts.'],
                ],
                'data' => [
                    'conflicts' => $conflicts,
                ],
            ], 409);
        }

        $capacity = max(1, (int) ($data['capacity'] ?? 1));
        abort_if($capacity > 1 && ! empty($data['user_id']), 422, 'A member can only be assigned to one capacity slot.');
        unset($data['capacity']);
        $assignments = DB::transaction(function () use ($data, $capacity, $club, $request) {
            return collect(range(1, $capacity))->map(fn () => ClubStaffAssignment::query()->create([
                ...$data,
                'club_id' => $club->id,
                'assigned_by' => $request->user()->id,
            ]));
        });
        $assignment = $assignments->first();

        return response()->json([
            'data' => [
                ...$this->scheduling->assignmentPayload($assignment->load(['event:id,title', 'team:id,name', 'user:id,name', 'substitute:id,name'])),
                'conflicts' => $conflicts,
                'capacity' => $capacity,
                'slot_ids' => $assignments->pluck('id')->values(),
            ],
        ], 201);
    }

    public function conflicts(Request $request, Club $club)
    {
        $this->authorizeView($request, $club);
        $data = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'assignment_id' => ['nullable', 'integer'],
            'required_qualifications' => ['nullable', 'array', 'max:20'],
            'required_qualifications.*' => ['string', 'max:160'],
        ]);
        if (! empty($data['user_id'])) {
            $this->assertClubUser($club, (int) $data['user_id']);
        }

        abort_if(empty($data['user_id']), 422, 'A member is required for a conflict check.');

        $conflicts = $this->scheduling->conflictsFor(
            $club,
            (int) $data['user_id'],
            $request->date('starts_at'),
            $request->date('ends_at'),
            $data['assignment_id'] ?? null,
            $data['required_qualifications'] ?? [],
        );

        return response()->json(['data' => [
            'conflicts' => $conflicts,
            'has_blocking_conflicts' => $this->hasBlockingConflicts($conflicts),
        ]]);
    }

    public function signupAssignment(Request $request, Club $club, ClubStaffAssignment $assignment)
    {
        $this->authorizeView($request, $club);
        $this->assertAssignmentBelongsToClub($assignment, $club);
        $userId = $request->user()->id;
        $this->assertActiveClubUser($club, $userId);

        return DB::transaction(function () use ($request, $club, $assignment, $userId) {
            $assignment = ClubStaffAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $this->assertAssignmentBelongsToClub($assignment, $club);

            if ((int) $assignment->user_id === $userId && in_array($assignment->status, ['planned', 'confirmed'], true)) {
                return response()->json(['data' => $this->scheduling->assignmentPayload($assignment->load(['event:id,title', 'team:id,name']))]);
            }

            if ($assignment->user_id !== null || ! in_array($assignment->status, ['open', 'planned'], true)) {
                return $this->createWaitlistEntry($request, $club, $assignment, $userId);
            }

            $conflicts = $this->scheduling->conflictsFor($club, $userId, $assignment->starts_at, $assignment->ends_at, $assignment->id, $assignment->required_qualifications ?? []);
            if ($this->hasBlockingConflicts($conflicts) && ! $request->boolean('force')) {
                return $this->conflictResponse($conflicts);
            }

            $assignment->update([
                'user_id' => $userId,
                'status' => 'confirmed',
            ]);

            return response()->json(['data' => [
                ...$this->scheduling->assignmentPayload($assignment->refresh()->load(['event:id,title', 'team:id,name'])),
                'conflicts' => $conflicts,
            ]]);
        });
    }

    public function waitlistAssignment(Request $request, Club $club, ClubStaffAssignment $assignment)
    {
        $this->authorizeView($request, $club);
        $this->assertAssignmentBelongsToClub($assignment, $club);
        $this->assertActiveClubUser($club, $request->user()->id);

        return DB::transaction(fn () => $this->createWaitlistEntry($request, $club, $assignment, $request->user()->id));
    }

    public function releaseAssignment(Request $request, Club $club, ClubStaffAssignment $assignment)
    {
        $this->authorizeAssignmentActor($request, $club, $assignment);

        return DB::transaction(function () use ($club, $assignment) {
            $assignment = ClubStaffAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $this->assertAssignmentBelongsToClub($assignment, $club);
            $previousUserId = $assignment->user_id;

            ClubStaffChangeRequest::query()
                ->where('assignment_id', $assignment->id)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled', 'responded_at' => now()]);

            if ($assignment->status === 'waitlisted') {
                $assignment->update(['status' => 'cancelled']);

                return response()->json(['data' => [
                    'released_user_id' => $previousUserId,
                    'assignment' => $this->scheduling->assignmentPayload($assignment->refresh()->load(['event:id,title', 'team:id,name', 'user:id,name', 'substitute:id,name'])),
                    'promoted_assignment' => null,
                ]]);
            }

            $assignment->update([
                'user_id' => null,
                'substitute_user_id' => null,
                'status' => 'open',
            ]);

            $promoted = $this->promoteNextWaitlisted($club, $assignment);

            return response()->json(['data' => [
                'released_user_id' => $previousUserId,
                'assignment' => $this->scheduling->assignmentPayload($assignment->refresh()->load(['event:id,title', 'team:id,name'])),
                'promoted_assignment' => $promoted ? $this->scheduling->assignmentPayload($promoted->load(['event:id,title', 'team:id,name'])) : null,
            ]]);
        });
    }

    public function swapAssignment(Request $request, Club $club, ClubStaffAssignment $assignment)
    {
        $this->authorizeAssignmentActor($request, $club, $assignment);
        $data = $request->validate([
            'target_user_id' => ['required', 'integer'],
            'force' => ['nullable', 'boolean'],
        ]);
        $this->assertClubUser($club, (int) $data['target_user_id']);

        return $this->createChangeRequest($request, $club, $assignment, 'swap', (int) $data['target_user_id']);
    }

    public function substituteAssignment(Request $request, Club $club, ClubStaffAssignment $assignment)
    {
        $this->authorizeAssignmentActor($request, $club, $assignment);
        $data = $request->validate([
            'substitute_user_id' => ['required', 'integer'],
            'force' => ['nullable', 'boolean'],
        ]);
        $this->assertClubUser($club, (int) $data['substitute_user_id']);

        return $this->createChangeRequest($request, $club, $assignment, 'substitute', (int) $data['substitute_user_id']);
    }

    public function respondToChangeRequest(Request $request, Club $club, ClubStaffChangeRequest $changeRequest)
    {
        $this->authorizeView($request, $club);
        abort_unless((int) $changeRequest->club_id === (int) $club->id, 404);
        abort_unless((int) $changeRequest->target_user_id === (int) $request->user()?->id, 403);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['accept', 'decline'])],
        ]);

        return DB::transaction(function () use ($request, $club, $changeRequest, $data) {
            $changeRequest = ClubStaffChangeRequest::query()
                ->whereKey($changeRequest->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($changeRequest->status === 'pending', 409, 'This request has already been answered.');

            $assignment = ClubStaffAssignment::query()
                ->whereKey($changeRequest->assignment_id)
                ->lockForUpdate()
                ->firstOrFail();
            $this->assertAssignmentBelongsToClub($assignment, $club);

            if ($data['decision'] === 'accept') {
                $conflicts = $this->scheduling->conflictsFor(
                    $club,
                    (int) $changeRequest->target_user_id,
                    $assignment->starts_at,
                    $assignment->ends_at,
                    $assignment->id,
                    $assignment->required_qualifications ?? [],
                );
                if ($this->hasBlockingConflicts($conflicts)) {
                    return $this->conflictResponse($conflicts);
                }

                $assignment->update($changeRequest->type === 'swap'
                    ? [
                        'substitute_user_id' => $assignment->user_id,
                        'user_id' => $changeRequest->target_user_id,
                        'status' => 'confirmed',
                    ]
                    : [
                        'substitute_user_id' => $changeRequest->target_user_id,
                        'status' => 'confirmed',
                    ]);
            } else {
                $assignment->update(['status' => 'confirmed']);
            }

            $changeRequest->update([
                'status' => $data['decision'] === 'accept' ? 'accepted' : 'declined',
                'responded_at' => now(),
                'responded_by' => $request->user()->id,
            ]);
            ClubStaffChangeRequest::query()
                ->where('assignment_id', $assignment->id)
                ->whereKeyNot($changeRequest->id)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled', 'responded_at' => now()]);

            return response()->json(['data' => [
                'request' => $this->changeRequestPayload($changeRequest->refresh()->load(['requester:id,name', 'target:id,name'])),
                'assignment' => $this->scheduling->assignmentPayload($assignment->refresh()->load(['event:id,title', 'team:id,name', 'user:id,name', 'substitute:id,name'])),
            ]]);
        });
    }

    private function createChangeRequest(Request $request, Club $club, ClubStaffAssignment $assignment, string $type, int $targetUserId)
    {
        $this->assertAssignmentBelongsToClub($assignment, $club);
        abort_if($assignment->user_id === null || ! in_array($assignment->status, ['planned', 'confirmed', 'swap_requested'], true), 409, 'Only an occupied assignment can be transferred.');
        abort_if((int) $assignment->user_id === $targetUserId, 422, 'The target member is already assigned.');

        $pending = ClubStaffChangeRequest::query()
            ->where('assignment_id', $assignment->id)
            ->where('status', 'pending')
            ->first();
        if ($pending) {
            abort_unless($pending->type === $type && (int) $pending->target_user_id === $targetUserId, 409, 'This assignment already has a pending change request.');

            return response()->json(['data' => $this->changeRequestPayload(
                $pending->load(['requester:id,name', 'target:id,name']),
            )]);
        }

        $conflicts = $this->scheduling->conflictsFor(
            $club,
            $targetUserId,
            $assignment->starts_at,
            $assignment->ends_at,
            $assignment->id,
            $assignment->required_qualifications ?? [],
        );
        if ($this->hasBlockingConflicts($conflicts) && ! $request->boolean('force')) {
            return $this->conflictResponse($conflicts);
        }

        $changeRequest = ClubStaffChangeRequest::query()->create([
            'club_id' => $club->id,
            'assignment_id' => $assignment->id,
            'target_user_id' => $targetUserId,
            'type' => $type,
            'status' => 'pending',
            'requested_by' => $request->user()->id,
            'note' => $request->string('note')->limit(2000)->toString() ?: null,
        ]);
        $assignment->update(['status' => 'swap_requested']);

        return response()->json(['data' => [
            ...$this->changeRequestPayload($changeRequest->load(['requester:id,name', 'target:id,name'])),
            'conflicts' => $conflicts,
        ]], 201);
    }

    private function changeRequestPayload(ClubStaffChangeRequest $changeRequest): array
    {
        return [
            'id' => $changeRequest->id,
            'assignment_id' => $changeRequest->assignment_id,
            'type' => $changeRequest->type,
            'status' => $changeRequest->status,
            'requested_by' => $changeRequest->requested_by,
            'requester_name' => $changeRequest->requester?->name,
            'target_user_id' => $changeRequest->target_user_id,
            'target_user_name' => $changeRequest->target?->name,
            'note' => $changeRequest->note,
            'responded_at' => $changeRequest->responded_at?->toJSON(),
            'created_at' => $changeRequest->created_at?->toJSON(),
        ];
    }

    private function availabilityData(Request $request, Club $club): array
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'status' => ['required', Rule::in(ClubStaffAvailability::STATUSES)],
            'source' => ['nullable', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        if (! empty($data['user_id'])) {
            $this->assertClubUser($club, (int) $data['user_id']);
        } elseif (! in_array($data['status'], ['open', 'planned'], true)) {
            abort(422);
        }

        return $data;
    }

    private function assignmentData(Request $request, Club $club): array
    {
        $data = $request->validate([
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'user_id' => ['nullable', 'integer'],
            'substitute_user_id' => ['nullable', 'integer'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'role' => ['required', 'string', 'max:80'],
            'required_qualifications' => ['nullable', 'array', 'max:20'],
            'required_qualifications.*' => ['string', 'max:160'],
            'status' => ['required', Rule::in(ClubStaffAssignment::STATUSES)],
            'note' => ['nullable', 'string', 'max:2000'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'force' => ['nullable', 'boolean'],
        ]);
        if (! empty($data['user_id'])) {
            $this->assertClubUser($club, (int) $data['user_id']);
        } elseif (! in_array($data['status'], ['open', 'planned'], true)) {
            abort(422);
        }

        if (! empty($data['substitute_user_id'])) {
            $this->assertClubUser($club, (int) $data['substitute_user_id']);
        }

        if (! empty($data['event_id'])) {
            abort_unless(Event::query()->where('club_id', $club->id)->whereKey($data['event_id'])->exists(), 422);
        }

        if (! empty($data['team_id'])) {
            abort_unless(Team::query()->where('club_id', $club->id)->whereKey($data['team_id'])->exists(), 422);
        }

        unset($data['force']);

        return [
            ...$data,
            'event_id' => $data['event_id'] ?? null,
            'team_id' => $data['team_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'substitute_user_id' => $data['substitute_user_id'] ?? null,
            'required_qualifications' => $data['required_qualifications'] ?? [],
        ];
    }

    private function authorizeView(Request $request, Club $club): void
    {
        abort_unless($request->user() && (
            $this->clubHasActiveUser($club, $request->user()->id)
            || ClubPermissions::allows($club, $request->user(), ClubPermissions::EVENTS_MANAGE)
        ), 403);
    }

    private function authorizeManage(Request $request, Club $club): void
    {
        abort_unless($this->canManage($request, $club), 403);
    }

    private function canManage(Request $request, Club $club): bool
    {
        return $request->user() && (
            ClubPermissions::allows($club, $request->user(), ClubPermissions::EVENTS_MANAGE)
            || ClubPermissions::allows($club, $request->user(), ClubPermissions::TRAINING_SESSIONS_EDIT)
            || ClubPermissions::allows($club, $request->user(), ClubPermissions::MEMBERS_EDIT)
        );
    }

    private function assertClubUser(Club $club, int $userId): void
    {
        $this->assertActiveClubUser($club, $userId);
    }

    private function assertActiveClubUser(Club $club, int $userId): void
    {
        abort_unless($club->users()
            ->where('users.id', $userId)
            ->wherePivot('membership_status', 'active')
            ->exists(), 422, 'An active club membership is required.');
    }

    private function hasBlockingConflicts(array $conflicts): bool
    {
        return collect($conflicts)->contains(fn (array $conflict) => ($conflict['severity'] ?? null) === 'blocking');
    }

    private function assertAssignmentBelongsToClub(ClubStaffAssignment $assignment, Club $club): void
    {
        abort_unless((int) $assignment->club_id === (int) $club->id, 404);
    }

    private function authorizeAssignmentActor(Request $request, Club $club, ClubStaffAssignment $assignment): void
    {
        $this->assertAssignmentBelongsToClub($assignment, $club);
        abort_unless($this->canManage($request, $club) || (int) $assignment->user_id === (int) $request->user()?->id, 403);
    }

    private function createWaitlistEntry(Request $request, Club $club, ClubStaffAssignment $assignment, int $userId)
    {
        $assignment = ClubStaffAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
        $this->assertAssignmentBelongsToClub($assignment, $club);

        $existing = ClubStaffAssignment::query()
            ->where('club_id', $club->id)
            ->where('user_id', $userId)
            ->where('status', 'waitlisted')
            ->where('starts_at', $assignment->starts_at)
            ->where('ends_at', $assignment->ends_at)
            ->where('role', $assignment->role)
            ->first();

        if ($existing) {
            return response()->json(['data' => $this->scheduling->assignmentPayload($existing->load(['event:id,title', 'team:id,name']))]);
        }

        $waitlisted = ClubStaffAssignment::query()->create([
            'club_id' => $club->id,
            'event_id' => $assignment->event_id,
            'team_id' => $assignment->team_id,
            'user_id' => $userId,
            'substitute_user_id' => null,
            'assigned_by' => $request->user()->id,
            'starts_at' => $assignment->starts_at,
            'ends_at' => $assignment->ends_at,
            'role' => $assignment->role,
            'required_qualifications' => $assignment->required_qualifications ?? [],
            'status' => 'waitlisted',
            'note' => $request->string('note')->limit(2000)->toString() ?: null,
        ]);

        return response()->json(['data' => $this->scheduling->assignmentPayload($waitlisted->load(['event:id,title', 'team:id,name']))], 201);
    }

    private function promoteNextWaitlisted(Club $club, ClubStaffAssignment $assignment): ?ClubStaffAssignment
    {
        $waitlisted = ClubStaffAssignment::query()
            ->where('club_id', $club->id)
            ->where('status', 'waitlisted')
            ->where('starts_at', $assignment->starts_at)
            ->where('ends_at', $assignment->ends_at)
            ->where('role', $assignment->role)
            ->orderBy('created_at')
            ->lockForUpdate()
            ->get();

        foreach ($waitlisted as $next) {
            if (! $this->clubHasActiveUser($club, (int) $next->user_id)) {
                $next->update(['status' => 'cancelled']);

                continue;
            }

            $conflicts = $this->scheduling->conflictsFor($club, (int) $next->user_id, $assignment->starts_at, $assignment->ends_at, $next->id, $assignment->required_qualifications ?? []);
            if ($this->hasBlockingConflicts($conflicts)) {
                continue;
            }

            $assignment->update([
                'user_id' => $next->user_id,
                'status' => 'confirmed',
            ]);
            $next->update(['status' => 'cancelled']);

            return $assignment->refresh();
        }

        return null;
    }

    private function clubHasActiveUser(Club $club, int $userId): bool
    {
        return $club->users()
            ->where('users.id', $userId)
            ->wherePivot('membership_status', 'active')
            ->exists();
    }

    private function conflictResponse(array $conflicts)
    {
        return response()->json([
            'message' => 'Assignment has blocking calendar, availability, or qualification conflicts.',
            'errors' => [
                'conflicts' => ['Assignment has blocking calendar, availability, or qualification conflicts.'],
            ],
            'data' => [
                'conflicts' => $conflicts,
            ],
        ], 409);
    }
}
