<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubStaffAssignment;
use App\Models\ClubStaffAvailability;
use App\Models\Event;
use App\Models\Team;
use App\Models\User;
use App\Services\ClubStaffSchedulingService;
use App\Support\ClubPermissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClubStaffSchedulingController extends Controller
{
    public function __construct(private ClubStaffSchedulingService $scheduling) {}

    public function index(Request $request, Club $club)
    {
        $this->authorizeView($request, $club);
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
            'can_manage' => $this->canManage($request, $club),
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

        $assignment = ClubStaffAssignment::query()->create([
            ...$data,
            'club_id' => $club->id,
            'assigned_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => [
                ...$this->scheduling->assignmentPayload($assignment->load(['event:id,title', 'team:id,name'])),
                'conflicts' => $conflicts,
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
        } elseif (! in_array($data['status'], ['open', 'planned'], true)) {
            abort(422);
        }

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

        return DB::transaction(fn () => $this->createWaitlistEntry($request, $club, $assignment, $request->user()->id));
    }

    public function releaseAssignment(Request $request, Club $club, ClubStaffAssignment $assignment)
    {
        $this->authorizeAssignmentActor($request, $club, $assignment);

        return DB::transaction(function () use ($club, $assignment) {
            $assignment = ClubStaffAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $this->assertAssignmentBelongsToClub($assignment, $club);
            $previousUserId = $assignment->user_id;
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

        return DB::transaction(function () use ($request, $club, $assignment, $data) {
            $assignment = ClubStaffAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $this->assertAssignmentBelongsToClub($assignment, $club);

            $conflicts = $this->scheduling->conflictsFor($club, (int) $data['target_user_id'], $assignment->starts_at, $assignment->ends_at, $assignment->id, $assignment->required_qualifications ?? []);
            if ($this->hasBlockingConflicts($conflicts) && ! $request->boolean('force')) {
                return $this->conflictResponse($conflicts);
            }

            $assignment->update([
                'substitute_user_id' => $assignment->user_id,
                'user_id' => (int) $data['target_user_id'],
                'status' => 'confirmed',
            ]);

            return response()->json(['data' => [
                ...$this->scheduling->assignmentPayload($assignment->refresh()->load(['event:id,title', 'team:id,name'])),
                'conflicts' => $conflicts,
            ]]);
        });
    }

    public function substituteAssignment(Request $request, Club $club, ClubStaffAssignment $assignment)
    {
        $this->authorizeAssignmentActor($request, $club, $assignment);
        $data = $request->validate([
            'substitute_user_id' => ['required', 'integer'],
            'force' => ['nullable', 'boolean'],
        ]);
        $this->assertClubUser($club, (int) $data['substitute_user_id']);

        return DB::transaction(function () use ($request, $club, $assignment, $data) {
            $assignment = ClubStaffAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $this->assertAssignmentBelongsToClub($assignment, $club);

            $conflicts = $this->scheduling->conflictsFor($club, (int) $data['substitute_user_id'], $assignment->starts_at, $assignment->ends_at, $assignment->id, $assignment->required_qualifications ?? []);
            if ($this->hasBlockingConflicts($conflicts) && ! $request->boolean('force')) {
                return $this->conflictResponse($conflicts);
            }

            $assignment->update([
                'substitute_user_id' => (int) $data['substitute_user_id'],
                'status' => 'confirmed',
            ]);

            return response()->json(['data' => [
                ...$this->scheduling->assignmentPayload($assignment->refresh()->load(['event:id,title', 'team:id,name'])),
                'conflicts' => $conflicts,
            ]]);
        });
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
            $this->clubHasUser($club, $request->user()->id)
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
        abort_unless($this->clubHasUser($club, $userId), 422);
    }

    private function clubHasUser(Club $club, int $userId): bool
    {
        return User::query()
            ->whereKey($userId)
            ->whereHas('clubs', fn ($query) => $query->where('clubs.id', $club->id))
            ->exists();
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
        $next = ClubStaffAssignment::query()
            ->where('club_id', $club->id)
            ->where('status', 'waitlisted')
            ->where('starts_at', $assignment->starts_at)
            ->where('ends_at', $assignment->ends_at)
            ->where('role', $assignment->role)
            ->orderBy('created_at')
            ->lockForUpdate()
            ->first();

        if (! $next) {
            return null;
        }

        $conflicts = $this->scheduling->conflictsFor($club, (int) $next->user_id, $assignment->starts_at, $assignment->ends_at, $next->id, $assignment->required_qualifications ?? []);
        if ($this->hasBlockingConflicts($conflicts)) {
            return null;
        }

        $assignment->update([
            'user_id' => $next->user_id,
            'status' => 'confirmed',
        ]);
        $next->update(['status' => 'cancelled']);

        return $assignment->refresh();
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
