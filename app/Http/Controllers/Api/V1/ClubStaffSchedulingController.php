<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Models\ClubStaffAssignment;
use App\Models\ClubStaffAvailability;
use App\Models\ClubStaffChangeRequest;
use App\Models\ClubStaffShiftComment;
use App\Models\Event;
use App\Models\Team;
use App\Models\User;
use App\Services\ClubStaffSchedulingService;
use App\Support\AppNotification;
use App\Support\ClubPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

        $calendar = $this->scheduling->calendarFor($club, $from, $to);
        if (! $canManage) {
            $calendar['assignments'] = $calendar['assignments']->filter(fn (array $assignment) => $this->canViewAssignmentPayload($club, $request->user(), $assignment))->values();
            $calendar['unfilled_assignments'] = $calendar['unfilled_assignments']->filter(fn (array $assignment) => $this->canViewAssignmentPayload($club, $request->user(), $assignment))->values();
        }
        $shiftKeys = $calendar['assignments']->pluck('shift_key')->filter()->unique();
        $comments = ClubStaffShiftComment::query()
            ->with('user:id,name')
            ->where('club_id', $club->id)
            ->whereIn('shift_key', $shiftKeys)
            ->oldest()
            ->get()
            ->groupBy('shift_key');
        $calendar['assignments'] = $calendar['assignments']->map(function (array $assignment) use ($comments, $canManage, $request): array {
            $assignment['can_edit'] = $canManage
                || (int) ($assignment['created_by'] ?? 0) === (int) $request->user()?->id;
            $assignment['comments'] = $comments->get($assignment['shift_key'], collect())->map(fn (ClubStaffShiftComment $comment) => [
                'id' => $comment->id,
                'message' => $comment->message,
                'created_at' => $comment->created_at?->toJSON(),
                'user' => $comment->user ? ['id' => $comment->user->id, 'name' => $comment->user->name] : null,
            ])->values();

            return $assignment;
        });
        $members = $canManage ? $this->relatedAudienceMembers($club) : collect();
        $externalMembers = $canManage
            ? ClubExternalMember::query()
                ->where('club_id', $club->id)
                ->where('membership_status', 'active')
                ->orderBy('name')
                ->get(['id', 'linked_user_id', 'name', 'email'])
            : collect();

        return response()->json(['data' => [
            ...$calendar,
            'range' => [
                'from' => $from->toJSON(),
                'to' => $to->toJSON(),
            ],
            'can_manage' => $canManage,
            'current_user_id' => $request->user()?->id,
            'members' => $members,
            'external_members' => $externalMembers,
            'audience_people' => $members
                ->map(fn (array $member) => [
                    ...$member,
                    'user_id' => $member['id'],
                    'kind' => 'account',
                    'selectable' => true,
                    'subtitle' => 'Mit Airmius-Konto',
                ])
                ->concat($externalMembers
                    ->whereNull('linked_user_id')
                    ->map(fn (ClubExternalMember $member) => [
                        'id' => 'external:'.$member->id,
                        'user_id' => null,
                        'name' => $member->name ?: $member->email,
                        'kind' => 'external',
                        'selectable' => false,
                        'subtitle' => 'Externes Mitglied ohne Airmius-Konto',
                    ]))
                ->values(),
            'teams' => Team::query()->where('club_id', $club->id)->orderBy('name')->get(['id', 'name']),
            'events' => Event::query()
                ->where('club_id', $club->id)
                ->where('status', '!=', 'cancelled')
                ->where('end_time', '>=', now()->subDay())
                ->orderBy('start_time')
                ->get(['id', 'title', 'start_time', 'end_time']),
            'change_requests' => ClubStaffChangeRequest::query()
                ->with(['requester:id,name', 'target:id,name', 'assignment:id,role,starts_at,ends_at'])
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
        $shiftKey = (string) Str::uuid();
        $assignments = DB::transaction(function () use ($data, $capacity, $club, $request, $shiftKey) {
            return collect(range(1, $capacity))->map(fn () => ClubStaffAssignment::query()->create([
                ...$data,
                'club_id' => $club->id,
                'shift_key' => $shiftKey,
                'assigned_by' => $request->user()->id,
            ]));
        });
        $assignment = $assignments->first();
        $this->notifyShiftAudience($club, $assignment, $request->user()->id);

        return response()->json([
            'data' => [
                ...$this->scheduling->assignmentPayload($assignment->load(['event:id,title', 'team:id,name', 'user:id,name', 'substitute:id,name'])),
                'conflicts' => $conflicts,
                'capacity' => $capacity,
                'slot_ids' => $assignments->pluck('id')->values(),
            ],
        ], 201);
    }

    public function updateAssignment(Request $request, Club $club, ClubStaffAssignment $assignment)
    {
        $this->authorizeShiftEditor($request, $club, $assignment);
        $data = $this->assignmentData($request, $club);
        $capacity = max(1, (int) ($data['capacity'] ?? 1));
        unset($data['capacity'], $data['user_id'], $data['substitute_user_id'], $data['status']);
        $shiftKey = $assignment->shift_key ?: (string) Str::uuid();

        $updated = DB::transaction(function () use ($club, $assignment, $data, $capacity, $shiftKey, $request) {
            $slots = $this->shiftSlots($club, $assignment)->lockForUpdate()->get();
            $occupied = $slots->filter(fn (ClubStaffAssignment $slot) => $slot->status !== 'waitlisted' && $this->slotOccupied($slot));
            abort_if($capacity < $occupied->count(), 422, 'Die Kapazität darf nicht kleiner als die aktuelle Belegung sein.');

            $slots->each->update([...$data, 'shift_key' => $shiftKey]);
            $currentCapacity = $slots->where('status', '!=', 'waitlisted')->count();
            if ($capacity > $currentCapacity) {
                collect(range(1, $capacity - $currentCapacity))->each(fn () => ClubStaffAssignment::query()->create([
                    ...$data,
                    'club_id' => $club->id,
                    'shift_key' => $shiftKey,
                    'assigned_by' => $assignment->assigned_by ?: $request->user()->id,
                    'user_id' => null,
                    'external_member_id' => null,
                    'substitute_user_id' => null,
                    'status' => 'open',
                ]));
            } elseif ($capacity < $currentCapacity) {
                $slots->filter(fn (ClubStaffAssignment $slot) => ! $this->slotOccupied($slot) && $slot->status !== 'waitlisted')
                    ->take($currentCapacity - $capacity)
                    ->each->delete();
            }

            return ClubStaffAssignment::query()->where('club_id', $club->id)->where('shift_key', $shiftKey)->oldest('id')->firstOrFail();
        });

        $this->notifyShiftParticipants($club, $updated, $request->user()->id, 'Einsatz wurde aktualisiert.');

        return response()->json(['data' => $this->scheduling->assignmentPayload($updated->load(['event:id,title', 'team:id,name', 'user:id,name', 'externalMember:id,name', 'assignedBy:id,name']))]);
    }

    public function destroyAssignment(Request $request, Club $club, ClubStaffAssignment $assignment)
    {
        $this->authorizeShiftEditor($request, $club, $assignment);
        $this->notifyShiftParticipants($club, $assignment, $request->user()->id, 'Einsatz wurde abgesagt.');
        DB::transaction(function () use ($club, $assignment): void {
            ClubStaffShiftComment::query()->where('club_id', $club->id)->where('shift_key', $assignment->shift_key)->delete();
            $this->shiftSlots($club, $assignment)->delete();
        });

        return response()->json(['message' => 'Einsatz wurde gelöscht.']);
    }

    public function commentAssignment(Request $request, Club $club, ClubStaffAssignment $assignment)
    {
        $this->authorizeView($request, $club);
        $this->assertAssignmentBelongsToClub($assignment, $club);
        abort_unless($this->canManage($request, $club) || $this->canViewAssignment($club, $request->user(), $assignment), 403);
        $data = $request->validate(['message' => ['required', 'string', 'max:2000']]);
        $comment = ClubStaffShiftComment::query()->create([
            'club_id' => $club->id,
            'shift_key' => $assignment->shift_key,
            'user_id' => $request->user()->id,
            'message' => trim($data['message']),
        ])->load('user:id,name');
        $this->notifyShiftParticipants($club, $assignment, $request->user()->id, 'Neue Frage oder Antwort zum Einsatz.');

        return response()->json(['data' => [
            'id' => $comment->id,
            'message' => $comment->message,
            'created_at' => $comment->created_at?->toJSON(),
            'user' => ['id' => $comment->user->id, 'name' => $comment->user->name],
        ]], 201);
    }

    public function assignExternalMember(Request $request, Club $club, ClubStaffAssignment $assignment)
    {
        $this->authorizeShiftEditor($request, $club, $assignment);
        $data = $request->validate(['external_member_id' => ['required', 'integer']]);
        $external = ClubExternalMember::query()->where('club_id', $club->id)->where('membership_status', 'active')->findOrFail($data['external_member_id']);
        $slot = DB::transaction(function () use ($club, $assignment, $external): ClubStaffAssignment {
            $lockedAssignment = ClubStaffAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            $slot = ! $this->slotOccupied($lockedAssignment) && in_array($lockedAssignment->status, ['open', 'planned'], true)
                ? $lockedAssignment
                : $this->shiftSlots($club, $lockedAssignment)->whereNull('user_id')->whereNull('external_member_id')->whereIn('status', ['open', 'planned'])->lockForUpdate()->first();
            abort_unless($slot, 409, 'Dieser Einsatz ist bereits vollständig besetzt.');
            $slot->update(['external_member_id' => $external->id, 'status' => 'confirmed']);

            return $slot;
        });

        return response()->json(['data' => $this->scheduling->assignmentPayload($slot->refresh()->load(['event:id,title', 'team:id,name', 'externalMember:id,name']))]);
    }

    public function offerAlternative(Request $request, Club $club, ClubStaffAssignment $assignment)
    {
        $this->authorizeShiftEditor($request, $club, $assignment);
        $data = $request->validate([
            'target_user_id' => ['required', 'integer'],
            'source_assignment_id' => ['required', 'integer'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $source = ClubStaffAssignment::query()->where('club_id', $club->id)->whereKey($data['source_assignment_id'])->firstOrFail();
        abort_unless($source->status === 'waitlisted' && (int) $source->user_id === (int) $data['target_user_id'], 422, 'Die Person steht nicht auf der angegebenen Warteliste.');
        abort_unless(! $this->slotOccupied($assignment) && in_array($assignment->status, ['open', 'planned'], true), 409, 'Der alternative Einsatz ist nicht mehr frei.');
        abort_unless($assignment->starts_at->equalTo($source->starts_at) && $assignment->ends_at->equalTo($source->ends_at), 422, 'Alternativangebote müssen im selben Zeitraum liegen.');
        abort_if(ClubStaffChangeRequest::query()
            ->where('club_id', $club->id)
            ->where('source_assignment_id', $source->id)
            ->where('target_user_id', $data['target_user_id'])
            ->where('type', 'alternative')
            ->where('status', 'pending')
            ->exists(), 409, 'Für diese Person ist bereits ein Alternativvorschlag offen.');

        $changeRequest = ClubStaffChangeRequest::query()->create([
            'club_id' => $club->id,
            'assignment_id' => $assignment->id,
            'source_assignment_id' => $source->id,
            'target_user_id' => $data['target_user_id'],
            'type' => 'alternative',
            'status' => 'pending',
            'requested_by' => $request->user()->id,
            'note' => $data['note'] ?? null,
        ]);
        AppNotification::send($data['target_user_id'], 'club.staff.alternative_offered', [
            'title' => 'Alternativer Einsatz vorgeschlagen',
            'body' => "Der Verein schlägt dir den Einsatz {$assignment->role} vor.",
            'club_id' => $club->id,
            'change_request_id' => $changeRequest->id,
            'url' => "/club-staff-scheduling?club_id={$club->id}",
            'mobile_url' => "airmius://clubs/{$club->id}/staff-scheduling",
            'deep_link' => "airmius://clubs/{$club->id}/staff-scheduling",
        ]);

        return response()->json(['data' => $this->changeRequestPayload($changeRequest->load(['requester:id,name', 'target:id,name']))], 201);
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
        abort_unless($this->canViewAssignment($club, $request->user(), $assignment), 403);
        abort_if($assignment->signup_deadline?->isPast(), 422, 'Die Anmeldefrist ist abgelaufen.');
        $userId = $request->user()->id;
        $this->assertActiveClubUser($club, $userId);

        return DB::transaction(function () use ($request, $club, $assignment, $userId) {
            $assignment = ClubStaffAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $this->assertAssignmentBelongsToClub($assignment, $club);

            if ((int) $assignment->user_id === $userId && in_array($assignment->status, ['planned', 'confirmed'], true)) {
                return response()->json(['data' => $this->scheduling->assignmentPayload($assignment->load(['event:id,title', 'team:id,name']))]);
            }

            if ($this->slotOccupied($assignment) || ! in_array($assignment->status, ['open', 'planned'], true)) {
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

                if ($changeRequest->type === 'alternative') {
                    abort_if($this->slotOccupied($assignment) || ! in_array($assignment->status, ['open', 'planned'], true), 409, 'Der vorgeschlagene Einsatz ist nicht mehr frei.');
                    $assignment->update([
                        'user_id' => $changeRequest->target_user_id,
                        'external_member_id' => null,
                        'status' => 'confirmed',
                    ]);
                    if ($changeRequest->source_assignment_id) {
                        ClubStaffAssignment::query()
                            ->whereKey($changeRequest->source_assignment_id)
                            ->where('status', 'waitlisted')
                            ->where('user_id', $changeRequest->target_user_id)
                            ->update(['status' => 'cancelled']);
                    }
                } else {
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
                }
            } elseif ($changeRequest->type !== 'alternative') {
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

            if ((int) $changeRequest->requested_by !== (int) $request->user()->id) {
                AppNotification::send($changeRequest->requested_by, 'club.staff.change_answered', [
                    'title' => $data['decision'] === 'accept' ? 'Einsatzanfrage angenommen' : 'Einsatzanfrage abgelehnt',
                    'body' => $data['decision'] === 'accept'
                        ? 'Die vorgeschlagene Änderung wurde bestätigt.'
                        : 'Die vorgeschlagene Änderung wurde abgelehnt.',
                    'club_id' => $club->id,
                    'change_request_id' => $changeRequest->id,
                    'url' => "/club-staff-scheduling?club_id={$club->id}",
                    'mobile_url' => "airmius://clubs/{$club->id}/staff-scheduling",
                    'deep_link' => "airmius://clubs/{$club->id}/staff-scheduling",
                ]);
            }

            return response()->json(['data' => [
                'request' => $this->changeRequestPayload($changeRequest->refresh()->load(['requester:id,name', 'target:id,name', 'assignment:id,role,starts_at,ends_at'])),
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
            'source_assignment_id' => $changeRequest->source_assignment_id,
            'assignment_role' => $changeRequest->assignment?->role,
            'assignment_starts_at' => $changeRequest->assignment?->starts_at?->toJSON(),
            'assignment_ends_at' => $changeRequest->assignment?->ends_at?->toJSON(),
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
            'signup_deadline' => ['nullable', 'date', 'before_or_equal:starts_at'],
            'role' => ['required', 'string', 'max:80'],
            'location' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:4000'],
            'requirements' => ['nullable', 'string', 'max:4000'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_details' => ['nullable', 'string', 'max:255'],
            'visibility_scope' => ['nullable', Rule::in(['club', 'teams', 'people'])],
            'audience_team_ids' => ['exclude_unless:visibility_scope,teams', 'required', 'array', 'min:1', 'max:50'],
            'audience_team_ids.*' => ['integer'],
            'audience_user_ids' => ['exclude_unless:visibility_scope,people', 'required', 'array', 'min:1', 'max:500'],
            'audience_user_ids.*' => ['integer'],
            'notify_audience' => ['nullable', 'boolean'],
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
        foreach ($data['audience_team_ids'] ?? [] as $teamId) {
            abort_unless(Team::query()->where('club_id', $club->id)->whereKey($teamId)->exists(), 422);
        }
        foreach ($data['audience_user_ids'] ?? [] as $userId) {
            $this->assertClubUser($club, (int) $userId);
        }

        unset($data['force']);

        return [
            ...$data,
            'event_id' => $data['event_id'] ?? null,
            'team_id' => $data['team_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'substitute_user_id' => $data['substitute_user_id'] ?? null,
            'required_qualifications' => $data['required_qualifications'] ?? [],
            'visibility_scope' => $data['visibility_scope'] ?? 'club',
            'audience_team_ids' => $data['audience_team_ids'] ?? [],
            'audience_user_ids' => $data['audience_user_ids'] ?? [],
            'notify_audience' => $data['notify_audience'] ?? true,
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
        abort_unless($this->clubHasActiveUser($club, $userId), 422, 'An active club membership is required.');
    }

    private function relatedAudienceMembers(Club $club): Collection
    {
        $userIds = $club->users()
            ->where(fn ($query) => $query
                ->whereNull('club_user.membership_status')
                ->orWhere('club_user.membership_status', 'active'))
            ->pluck('users.id')
            ->push($club->owner_id)
            ->merge(ClubExternalMember::query()
                ->where('club_id', $club->id)
                ->where('membership_status', 'active')
                ->whereNotNull('linked_user_id')
                ->pluck('linked_user_id'))
            ->filter()
            ->unique();

        return User::query()
            ->whereIn('id', $userIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
            ->values();
    }

    private function hasBlockingConflicts(array $conflicts): bool
    {
        return collect($conflicts)->contains(fn (array $conflict) => ($conflict['severity'] ?? null) === 'blocking');
    }

    private function assertAssignmentBelongsToClub(ClubStaffAssignment $assignment, Club $club): void
    {
        abort_unless((int) $assignment->club_id === (int) $club->id, 404);
    }

    private function authorizeShiftEditor(Request $request, Club $club, ClubStaffAssignment $assignment): void
    {
        $this->assertAssignmentBelongsToClub($assignment, $club);
        $isCreator = $this->shiftSlots($club, $assignment)
            ->where('status', '!=', 'waitlisted')
            ->where('assigned_by', $request->user()?->id)
            ->exists();
        abort_unless($this->canManage($request, $club) || $isCreator, 403);
    }

    private function shiftSlots(Club $club, ClubStaffAssignment $assignment)
    {
        $query = ClubStaffAssignment::query()->where('club_id', $club->id);
        if ($assignment->shift_key) {
            return $query->where('shift_key', $assignment->shift_key);
        }

        return $query
            ->where('role', $assignment->role)
            ->where('starts_at', $assignment->starts_at)
            ->where('ends_at', $assignment->ends_at)
            ->where('event_id', $assignment->event_id)
            ->where('team_id', $assignment->team_id);
    }

    private function slotOccupied(ClubStaffAssignment $assignment): bool
    {
        return $assignment->user_id !== null || $assignment->external_member_id !== null;
    }

    private function canViewAssignmentPayload(Club $club, User $user, array $assignment): bool
    {
        if ((int) ($assignment['user_id'] ?? 0) === (int) $user->id
            || (int) ($assignment['created_by'] ?? 0) === (int) $user->id) {
            return true;
        }
        $scope = $assignment['visibility_scope'] ?? 'club';
        if ($scope === 'club') {
            return true;
        }
        if ($scope === 'people') {
            return in_array((int) $user->id, array_map('intval', $assignment['audience_user_ids'] ?? []), true);
        }

        return Team::query()
            ->where('club_id', $club->id)
            ->whereIn('id', $assignment['audience_team_ids'] ?? [])
            ->whereHas('users', fn ($query) => $query->where('users.id', $user->id))
            ->exists();
    }

    private function canViewAssignment(Club $club, User $user, ClubStaffAssignment $assignment): bool
    {
        return $this->canViewAssignmentPayload($club, $user, [
            'user_id' => $assignment->user_id,
            'created_by' => $assignment->assigned_by,
            'visibility_scope' => $assignment->visibility_scope,
            'audience_team_ids' => $assignment->audience_team_ids,
            'audience_user_ids' => $assignment->audience_user_ids,
        ]);
    }

    private function audienceRecipientIds(Club $club, ClubStaffAssignment $assignment): array
    {
        if ($assignment->visibility_scope === 'teams') {
            return Team::query()
                ->where('club_id', $club->id)
                ->whereIn('id', $assignment->audience_team_ids ?? [])
                ->with('users:id')
                ->get()
                ->flatMap->users
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }
        if ($assignment->visibility_scope === 'people') {
            return array_values(array_unique(array_map('intval', $assignment->audience_user_ids ?? [])));
        }

        return $this->relatedAudienceMembers($club)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function notifyShiftAudience(Club $club, ClubStaffAssignment $assignment, int $actorId): void
    {
        if (! $assignment->notify_audience) {
            return;
        }
        User::query()->whereIn('id', $this->audienceRecipientIds($club, $assignment))->whereKeyNot($actorId)->get()->each(
            fn (User $recipient) => AppNotification::send($recipient, 'club.staff.shift_published', [
                'title' => 'Neuer Einsatz verfügbar',
                'body' => "{$assignment->role} sucht Unterstützung.",
                'club_id' => $club->id,
                'assignment_id' => $assignment->id,
                'url' => "/club-staff-scheduling?club_id={$club->id}",
                'mobile_url' => "airmius://clubs/{$club->id}/staff-scheduling",
                'deep_link' => "airmius://clubs/{$club->id}/staff-scheduling",
            ]),
        );
    }

    private function notifyShiftParticipants(Club $club, ClubStaffAssignment $assignment, int $actorId, string $body): void
    {
        $recipientIds = $this->shiftSlots($club, $assignment)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->merge(ClubStaffShiftComment::query()->where('club_id', $club->id)->where('shift_key', $assignment->shift_key)->pluck('user_id'))
            ->push($assignment->assigned_by)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $id === $actorId)
            ->unique();
        User::query()->whereIn('id', $recipientIds)->get()->each(fn (User $recipient) => AppNotification::send($recipient, 'club.staff.shift_updated', [
            'title' => $assignment->role,
            'body' => $body,
            'club_id' => $club->id,
            'assignment_id' => $assignment->id,
            'url' => "/club-staff-scheduling?club_id={$club->id}",
            'mobile_url' => "airmius://clubs/{$club->id}/staff-scheduling",
            'deep_link' => "airmius://clubs/{$club->id}/staff-scheduling",
        ]));
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
            'assigned_by' => $assignment->assigned_by,
            'starts_at' => $assignment->starts_at,
            'ends_at' => $assignment->ends_at,
            'role' => $assignment->role,
            'required_qualifications' => $assignment->required_qualifications ?? [],
            'shift_key' => $assignment->shift_key,
            'visibility_scope' => $assignment->visibility_scope,
            'audience_team_ids' => $assignment->audience_team_ids ?? [],
            'audience_user_ids' => $assignment->audience_user_ids ?? [],
            'notify_audience' => $assignment->notify_audience,
            'location' => $assignment->location,
            'instructions' => $assignment->instructions,
            'requirements' => $assignment->requirements,
            'contact_name' => $assignment->contact_name,
            'contact_details' => $assignment->contact_details,
            'signup_deadline' => $assignment->signup_deadline,
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
        return (int) $club->owner_id === $userId
            || $club->users()
                ->where('users.id', $userId)
                ->where(fn ($query) => $query
                    ->whereNull('club_user.membership_status')
                    ->orWhere('club_user.membership_status', 'active'))
                ->exists()
            || ClubExternalMember::query()
                ->where('club_id', $club->id)
                ->where('linked_user_id', $userId)
                ->where('membership_status', 'active')
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
