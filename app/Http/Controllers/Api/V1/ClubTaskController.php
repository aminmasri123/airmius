<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\FileResource;
use App\Models\Club;
use App\Models\ClubTask;
use App\Models\ClubTaskComment;
use App\Models\Event;
use App\Models\Team;
use App\Models\User;
use App\Services\FileService;
use App\Support\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ClubTaskController extends Controller
{
    private const STATUSES = ['open', 'read', 'in_progress', 'waiting', 'done'];

    private const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    private const VISIBILITIES = ['personal', 'shared', 'team', 'club'];

    private const ASSIGNMENT_MODES = ['single', 'shared_all', 'open_claim'];

    public function __construct(private FileService $files) {}

    public function mine(Request $request)
    {
        $user = $request->user();
        $clubs = Club::query()
            ->linkedToUser($user)
            ->orderBy('name')
            ->get(['id', 'owner_id', 'name']);
        $tasks = collect();

        foreach ($clubs as $club) {
            $manager = $this->canManageClub($request, $club);
            $teamIds = $this->clubTeamIdsFor($club, $user);
            $clubTasks = ClubTask::query()
                ->where('club_id', $club->id)
                ->where(fn ($visible) => $manager
                    ? $this->managerVisibleTaskQuery($visible, $user)
                    : $this->visibleTaskQuery($visible, $user, $teamIds))
                ->with(['club:id,name', 'creator:id,name,profile_photo_path', 'assignee:id,name,profile_photo_path', 'team:id,name', 'comments.user:id,name,profile_photo_path', 'attachments'])
                ->withCount(['comments', 'attachments'])
                ->get()
                ->reject(fn (ClubTask $task) => ($task->assignment_status[(string) $user->id] ?? null) === 'declined');
            $tasks = $tasks->concat($clubTasks);
        }

        $tasks = $tasks
            ->sort(function (ClubTask $left, ClubTask $right): int {
                $leftDone = $left->completed_at !== null;
                $rightDone = $right->completed_at !== null;
                if ($leftDone !== $rightDone) {
                    return $leftDone <=> $rightDone;
                }

                $leftDue = $left->due_at?->format('Y-m-d');
                $rightDue = $right->due_at?->format('Y-m-d');
                if ($leftDue !== $rightDue) {
                    if ($leftDue === null) {
                        return 1;
                    }
                    if ($rightDue === null) {
                        return -1;
                    }

                    return $leftDue <=> $rightDue;
                }

                return $right->id <=> $left->id;
            })
            ->values()
            ->map(fn (ClubTask $task) => $this->taskPayload($task, $request));

        return response()->json([
            'data' => $tasks,
            'meta' => [
                'clubs' => $clubs->map(fn (Club $club) => [
                    'id' => $club->id,
                    'name' => $club->name,
                ])->values(),
                'statuses' => self::STATUSES,
                'priorities' => self::PRIORITIES,
                'visibilities' => self::VISIBILITIES,
                'assignment_modes' => self::ASSIGNMENT_MODES,
            ],
        ]);
    }

    public function index(Request $request, Club $club)
    {
        $this->authorizeTaskAccess($request, $club);
        $manager = $this->canManageClub($request, $club);
        $user = $request->user();
        $teamIds = $this->clubTeamIdsFor($club, $user);

        $tasks = ClubTask::query()
            ->where('club_id', $club->id)
            ->where(fn ($visible) => $manager
                ? $this->managerVisibleTaskQuery($visible, $user)
                : $this->visibleTaskQuery($visible, $user, $teamIds))
            ->with(['club:id,name', 'creator:id,name,profile_photo_path', 'assignee:id,name,profile_photo_path', 'team:id,name', 'comments.user:id,name,profile_photo_path', 'attachments'])
            ->withCount(['comments', 'attachments'])
            ->orderByRaw('completed_at IS NOT NULL')
            ->orderByRaw('due_at IS NULL')
            ->orderBy('due_at')
            ->orderByDesc('id')
            ->get()
            ->reject(fn (ClubTask $task) => ($task->assignment_status[(string) $user->id] ?? null) === 'declined')
            ->map(fn (ClubTask $task) => $this->taskPayload($task, $request));

        return response()->json([
            'data' => $tasks,
            'meta' => $this->metaPayload($club),
        ]);
    }

    public function store(Request $request, Club $club)
    {
        $this->authorizeTaskAccess($request, $club);
        $data = $this->validatedTaskData($request, $club);
        $data = $this->applyMemberTaskDefaults($request, $club, $data);
        $data = $this->normalizeAssignmentData($data);
        $task = ClubTask::create([
            'club_id' => $club->id,
            'created_by' => $request->user()->id,
            ...$this->taskAttributes($data),
            'assignment_status' => $this->initialAssignmentStatus($request, $data),
            'participant_progress' => $this->initialParticipantProgress($data),
            'activity_log' => [$this->activityEntry('created', $request->user())],
        ])->load(['club:id,name', 'creator:id,name,profile_photo_path', 'assignee:id,name,profile_photo_path', 'team:id,name', 'comments.user:id,name,profile_photo_path', 'attachments'])
            ->loadCount(['comments', 'attachments']);

        $this->notifyNewParticipants($task, $club, $request->user(), []);

        return response()->json(['data' => $this->taskPayload($task, $request)], 201);
    }

    public function update(Request $request, Club $club, ClubTask $task)
    {
        $this->authorizeTaskAccess($request, $club, $task, 'update');
        abort_unless((int) $task->club_id === (int) $club->id, 404);
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'status' => ['sometimes', 'required', Rule::in(self::STATUSES)],
            'priority' => ['sometimes', 'required', Rule::in(self::PRIORITIES)],
            'visibility' => ['sometimes', 'required', Rule::in(self::VISIBILITIES)],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')->where('club_id', $club->id)],
            'assigned_to' => ['nullable', 'integer'],
            'participant_ids' => ['nullable', 'array', 'max:50'],
            'participant_ids.*' => ['integer'],
            'assignment_mode' => ['sometimes', 'required', Rule::in(self::ASSIGNMENT_MODES)],
            'checklist' => ['nullable', 'array', 'max:50'],
            'checklist.*.title' => ['required_with:checklist', 'string', 'max:255'],
            'checklist.*.done' => ['nullable', 'boolean'],
            'attachment_links' => ['nullable', 'array', 'max:20'],
            'attachment_links.*.title' => ['nullable', 'string', 'max:160'],
            'attachment_links.*.url' => ['required_with:attachment_links', 'url:http,https', 'max:2048'],
            'start_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
            'completed' => ['sometimes', 'required', 'boolean'],
            'updated_at' => ['nullable', 'date'],
        ]);

        $this->ensureAssignableUsers($request, $club, $data);
        $this->ensureMemberCanSetVisibility($request, $club, $data);
        $isParticipantOnly = (int) $task->created_by !== (int) $request->user()->id
            && ! $this->canManageClub($request, $club)
            && $this->isAssignedParticipant($task, $request->user());
        $changesStatus = array_key_exists('status', $data) || array_key_exists('completed', $data);
        if ($isParticipantOnly && $changesStatus) {
            abort_if($this->effectiveAssignmentMode($task) === 'shared_all', 422, 'Use personal progress for shared assignments.');
        }
        $data = $this->normalizeAssignmentData($data, $task);
        $this->ensureNotStale($task, $data['updated_at'] ?? null);
        $previousAssignee = $task->assigned_to;
        $previousParticipantIds = $this->taskParticipantIds($task);
        $previousStatus = $task->status;
        $task->fill($this->taskAttributes($data, false));
        if (array_key_exists('assigned_to', $data) || array_key_exists('participant_ids', $data)) {
            $task->assignment_status = $this->mergedAssignmentStatus($task, $request, $data, $previousAssignee);
            $task->participant_progress = $this->mergedParticipantProgress($task, $data);
        }
        if (array_key_exists('completed', $data)) {
            $task->status = $data['completed'] ? 'done' : ($task->status === 'done' ? 'open' : $task->status);
            $task->completed_at = $data['completed'] ? now() : null;
        } elseif (array_key_exists('status', $data)) {
            $task->completed_at = $data['status'] === 'done' ? ($task->completed_at ?? now()) : null;
        }
        $activityType = $previousStatus !== $task->status ? 'status_changed' : 'updated';
        $this->appendActivity($task, $activityType, $request->user(), [
            'from' => $previousStatus,
            'to' => $task->status,
        ]);
        $task->save();
        $task->load(['club:id,name', 'creator:id,name,profile_photo_path', 'assignee:id,name,profile_photo_path', 'team:id,name', 'comments.user:id,name,profile_photo_path', 'attachments'])
            ->loadCount(['comments', 'attachments']);
        $this->notifyNewParticipants($task, $club, $request->user(), $previousParticipantIds);

        return response()->json(['data' => $this->taskPayload($task, $request)]);
    }

    public function destroy(Request $request, Club $club, ClubTask $task)
    {
        $this->authorizeTaskAccess($request, $club, $task);
        abort_unless((int) $task->club_id === (int) $club->id, 404);
        abort_unless($this->canManageClub($request, $club) || (int) $task->created_by === (int) $request->user()->id, 403);
        $task->delete();

        return response()->noContent();
    }

    public function updateProgress(Request $request, Club $club, ClubTask $task)
    {
        $this->authorizeTaskAccess($request, $club, $task, 'comment');
        abort_unless((int) $task->club_id === (int) $club->id, 404);
        abort_unless($this->isAssignedParticipant($task, $request->user()), 403);
        abort_if(in_array($task->assignment_status[(string) $request->user()->id] ?? null, ['pending', 'declined'], true), 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
        ]);
        $userId = (int) $request->user()->id;
        $progress = $task->participant_progress ?? [];
        $progress[(string) $userId] = $data['status'];
        $task->participant_progress = $progress;
        $this->syncTaskStatusFromParticipantProgress($task);
        $this->appendActivity($task, 'progress_changed', $request->user(), ['to' => $data['status']]);
        $task->save();
        $this->notifyProgressRecipients($task, $club, $request->user(), $data['status']);
        $task->load(['club:id,name', 'creator:id,name,profile_photo_path', 'assignee:id,name,profile_photo_path', 'team:id,name', 'comments.user:id,name,profile_photo_path', 'attachments'])
            ->loadCount(['comments', 'attachments']);

        return response()->json(['data' => $this->taskPayload($task, $request)]);
    }

    public function comment(Request $request, Club $club, ClubTask $task)
    {
        $this->authorizeTaskAccess($request, $club, $task, 'comment');
        abort_unless((int) $task->club_id === (int) $club->id, 404);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $comment = $task->comments()->create([
            'user_id' => $request->user()->id,
            'body' => trim($data['body']),
        ])->load('user:id,name,profile_photo_path');
        $this->appendActivity($task, 'commented', $request->user());
        $task->save();
        $this->notifyCommentRecipients($task, $club, $request->user(), $comment);

        return response()->json(['data' => $this->commentPayload($comment)], 201);
    }

    public function attach(Request $request, Club $club, ClubTask $task)
    {
        $this->authorizeTaskAccess($request, $club, $task, 'comment');
        abort_unless((int) $task->club_id === (int) $club->id, 404);
        $request->validate([
            'attachments' => ['required', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,gif,mp4,mov,webm,ogg,pdf,doc,docx,xls,xlsx,csv,txt,zip', 'max:51200'],
        ]);

        $uploaded = [];
        foreach ($request->file('attachments', []) as $upload) {
            $file = $this->files->upload($request->user(), $upload, [
                'club_id' => $club->id,
                'team_id' => $task->team_id,
            ]);
            $task->attachments()->syncWithoutDetaching([
                $file->id => ['uploaded_by' => $request->user()->id],
            ]);
            $uploaded[] = (new FileResource($file))->resolve($request);
        }
        $this->appendActivity($task, 'attachments_added', $request->user(), ['count' => count($uploaded)]);
        $task->save();

        return response()->json(['data' => $uploaded], 201);
    }

    public function detach(Request $request, Club $club, ClubTask $task, int $file)
    {
        $this->authorizeTaskAccess($request, $club, $task, 'update');
        abort_unless((int) $task->club_id === (int) $club->id, 404);
        $task->attachments()->detach($file);
        $this->appendActivity($task, 'attachment_removed', $request->user());
        $task->save();

        return response()->noContent();
    }

    public function acceptAssignment(Request $request, Club $club, ClubTask $task)
    {
        $this->authorizeTaskAccess($request, $club, $task, 'comment');
        abort_unless((int) $task->club_id === (int) $club->id, 404);
        abort_unless($this->isAssignedParticipant($task, $request->user()), 403);

        $status = $task->assignment_status ?? [];
        $userId = (int) $request->user()->id;
        $status[(string) $userId] = 'accepted';
        if ($this->effectiveAssignmentMode($task) === 'open_claim' && ! $task->assigned_to) {
            foreach ($status as $id => $value) {
                if ((int) $id !== $userId && $value === 'pending') {
                    $status[$id] = 'declined';
                }
            }
            $task->assigned_to = $userId;
            $task->participant_ids = [$userId];
        }
        $task->assignment_status = $status;
        $progress = $this->effectiveAssignmentMode($task) === 'open_claim'
            ? [(string) $userId => ($task->participant_progress[(string) $userId] ?? 'open')]
            : ($task->participant_progress ?? []);
        $progress[(string) $userId] ??= 'open';
        $task->participant_progress = $progress;
        $this->appendActivity($task, 'assignment_accepted', $request->user());
        $task->save();
        $this->notifyAssignmentResponse($task, $club, $request->user(), 'accepted');
        $task->load(['creator:id,name,profile_photo_path', 'assignee:id,name,profile_photo_path', 'team:id,name', 'comments.user:id,name,profile_photo_path', 'attachments'])
            ->loadCount(['comments', 'attachments']);

        return response()->json(['data' => $this->taskPayload($task, $request)]);
    }

    public function declineAssignment(Request $request, Club $club, ClubTask $task)
    {
        $this->authorizeTaskAccess($request, $club, $task, 'comment');
        abort_unless((int) $task->club_id === (int) $club->id, 404);
        abort_unless($this->isAssignedParticipant($task, $request->user()), 403);

        $this->removeParticipantFromTask($task, $request->user(), 'assignment_declined');
        $this->notifyAssignmentResponse($task, $club, $request->user(), 'declined');
        $task->load(['creator:id,name,profile_photo_path', 'assignee:id,name,profile_photo_path', 'team:id,name', 'comments.user:id,name,profile_photo_path', 'attachments'])
            ->loadCount(['comments', 'attachments']);

        return response()->json(['data' => $this->taskPayload($task, $request)]);
    }

    public function leave(Request $request, Club $club, ClubTask $task)
    {
        $this->authorizeTaskAccess($request, $club, $task, 'comment');
        abort_unless((int) $task->club_id === (int) $club->id, 404);
        abort_unless($this->canLeaveTask($task, $request->user()), 403);

        $this->removeParticipantFromTask($task, $request->user(), 'left');

        return response()->noContent();
    }

    private function validatedTaskData(Request $request, Club $club): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'priority' => ['nullable', Rule::in(self::PRIORITIES)],
            'visibility' => ['nullable', Rule::in(self::VISIBILITIES)],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')->where('club_id', $club->id)],
            'assigned_to' => ['nullable', 'integer'],
            'participant_ids' => ['nullable', 'array', 'max:50'],
            'participant_ids.*' => ['integer'],
            'assignment_mode' => ['nullable', Rule::in(self::ASSIGNMENT_MODES)],
            'checklist' => ['nullable', 'array', 'max:50'],
            'checklist.*.title' => ['required_with:checklist', 'string', 'max:255'],
            'checklist.*.done' => ['nullable', 'boolean'],
            'attachment_links' => ['nullable', 'array', 'max:20'],
            'attachment_links.*.title' => ['nullable', 'string', 'max:160'],
            'attachment_links.*.url' => ['required_with:attachment_links', 'url:http,https', 'max:2048'],
            'start_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
        ]);
        $this->ensureAssignableUsers($request, $club, $data);
        $this->ensureMemberCanSetVisibility($request, $club, $data);

        return $data;
    }

    private function authorizeTaskAccess(Request $request, Club $club, ?ClubTask $task = null, string $ability = 'read'): void
    {
        $user = $request->user();
        $canUseClubTasks = $this->canManageClub($request, $club)
            || (int) $club->owner_id === (int) $user->id
            || Club::query()
                ->linkedToUser($user)
                ->whereKey($club->id)
                ->exists();

        abort_unless($canUseClubTasks, 403);

        if (! $task) {
            return;
        }

        if ($ability !== 'update' && $this->canManageClub($request, $club) && in_array($task->visibility, ['club', 'team'], true)) {
            return;
        }

        abort_unless($this->canViewTask($club, $task, $user), 403);

        if ($ability === 'update') {
            abort_unless($this->canMutateTask($task, $user), 403);
        }
    }

    private function canManageClub(Request $request, Club $club): bool
    {
        return Gate::forUser($request->user())->allows('update', $club);
    }

    private function visibleTaskQuery($query, User $user, $teamIds): void
    {
        $query
            ->where('visibility', 'club')
            ->orWhere('created_by', $user->id)
            ->orWhere('assigned_to', $user->id)
            ->orWhereJsonContains('participant_ids', $user->id)
            ->orWhere(fn ($shared) => $shared
                ->where('visibility', 'shared')
                ->where(fn ($scope) => $scope
                    ->where('created_by', $user->id)
                    ->orWhere('assigned_to', $user->id)
                    ->orWhereJsonContains('participant_ids', $user->id)))
            ->orWhere(fn ($team) => $team
                ->where('visibility', 'team')
                ->whereIn('team_id', $teamIds));
    }

    private function managerVisibleTaskQuery($query, User $user): void
    {
        $query
            ->whereIn('visibility', ['club', 'team'])
            ->orWhere('created_by', $user->id)
            ->orWhere('assigned_to', $user->id)
            ->orWhereJsonContains('participant_ids', $user->id);
    }

    private function canViewTask(Club $club, ClubTask $task, User $user): bool
    {
        if ($this->canMutateTask($task, $user) || $this->isAssignedParticipant($task, $user)) {
            return true;
        }

        return match ($task->visibility) {
            'club' => true,
            'team' => $task->team_id && $this->clubTeamIdsFor($club, $user)->contains((int) $task->team_id),
            default => false,
        };
    }

    private function canMutateTask(ClubTask $task, User $user): bool
    {
        return (int) $task->created_by === (int) $user->id;
    }

    private function canDeleteTask(Request $request, Club $club, ClubTask $task): bool
    {
        return (int) $task->created_by === (int) $request->user()->id;
    }

    private function canLeaveTask(ClubTask $task, User $user): bool
    {
        return (int) $task->created_by !== (int) $user->id
            && $this->isAssignedParticipant($task, $user);
    }

    private function isAssignedParticipant(ClubTask $task, User $user): bool
    {
        return (int) $task->assigned_to === (int) $user->id
            || in_array((int) $user->id, array_map('intval', $task->participant_ids ?? []), true);
    }

    private function removeParticipantFromTask(ClubTask $task, User $user, string $activityType): void
    {
        $userId = (int) $user->id;
        $status = $task->assignment_status ?? [];
        $status[(string) $userId] = 'declined';
        $task->assignment_status = $status;
        if ((int) $task->assigned_to === $userId) {
            $task->assigned_to = null;
        }
        $task->participant_ids = collect($task->participant_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $id === $userId)
            ->values()
            ->all();
        $progress = $task->participant_progress ?? [];
        unset($progress[(string) $userId]);
        $task->participant_progress = $progress;
        $this->appendActivity($task, $activityType, $user);
        $task->save();
    }

    private function initialAssignmentStatus(Request $request, array $data): array
    {
        $actorId = (int) $request->user()->id;

        return collect($data['participant_ids'] ?? [])
            ->push($data['assigned_to'] ?? null)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->mapWithKeys(fn (int $id) => [(string) $id => $id === $actorId ? 'accepted' : 'pending'])
            ->all();
    }

    private function initialParticipantProgress(array $data): array
    {
        return $this->participantIdsFromData($data)
            ->mapWithKeys(fn (int $id) => [(string) $id => 'open'])
            ->all();
    }

    private function mergedParticipantProgress(ClubTask $task, array $data): array
    {
        $ids = $this->participantIdsFromData([
            'participant_ids' => $data['participant_ids'] ?? $task->participant_ids,
            'assigned_to' => array_key_exists('assigned_to', $data) ? $data['assigned_to'] : $task->assigned_to,
        ]);
        $progress = $task->participant_progress ?? [];

        return $ids->mapWithKeys(fn (int $id) => [
            (string) $id => $progress[(string) $id] ?? 'open',
        ])->all();
    }

    private function mergedAssignmentStatus(ClubTask $task, Request $request, array $data, ?int $previousAssignee): array
    {
        $status = $task->assignment_status ?? [];
        $actorId = (int) $request->user()->id;
        $currentIds = collect($data['participant_ids'] ?? $task->participant_ids ?? [])
            ->push(array_key_exists('assigned_to', $data) ? $data['assigned_to'] : $task->assigned_to)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        foreach ($currentIds as $id) {
            if ($id === $actorId) {
                $status[(string) $id] = 'accepted';

                continue;
            }

            $newPrimaryAssignee = array_key_exists('assigned_to', $data)
                && (int) ($data['assigned_to'] ?? 0) === $id
                && (int) $previousAssignee !== $id;
            if (! array_key_exists((string) $id, $status) || $newPrimaryAssignee) {
                $status[(string) $id] = 'pending';
            }
        }

        return collect($status)
            ->only($currentIds->map(fn (int $id) => (string) $id)->all())
            ->all();
    }

    private function clubTeamIdsFor(Club $club, User $user)
    {
        return Team::query()
            ->where('club_id', $club->id)
            ->whereHas('users', fn ($query) => $query->where('users.id', $user->id))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    private function ensureNotStale(ClubTask $task, ?string $updatedAt): void
    {
        if (! $updatedAt) {
            return;
        }

        $clientTimestamp = Carbon::parse($updatedAt)->utc();
        $serverTimestamp = $task->updated_at?->utc();
        abort_if($serverTimestamp && $clientTimestamp->lt($serverTimestamp), 409, 'Task was changed by someone else. Please reload and try again.');
    }

    private function taskAttributes(array $data, bool $withDefaults = true): array
    {
        $attributes = [];
        foreach (['title', 'description', 'status', 'priority', 'visibility', 'team_id', 'assigned_to', 'assignment_mode', 'start_at', 'due_at'] as $key) {
            if (array_key_exists($key, $data)) {
                $attributes[$key] = is_string($data[$key]) ? trim($data[$key]) : $data[$key];
            }
        }
        if ($withDefaults) {
            $attributes['status'] ??= 'open';
            $attributes['priority'] ??= 'normal';
            $attributes['visibility'] ??= 'club';
            $attributes['assignment_mode'] ??= $this->assignmentModeFromData($data);
        }
        if (array_key_exists('participant_ids', $data)) {
            $attributes['participant_ids'] = array_values(array_unique(array_map('intval', $data['participant_ids'] ?? [])));
        }
        if (array_key_exists('checklist', $data)) {
            $attributes['checklist'] = collect($data['checklist'] ?? [])
                ->map(fn (array $item) => [
                    'title' => trim((string) $item['title']),
                    'done' => (bool) ($item['done'] ?? false),
                ])
                ->filter(fn (array $item) => $item['title'] !== '')
                ->values()
                ->all();
        }
        if (array_key_exists('attachment_links', $data)) {
            $attributes['attachment_links'] = collect($data['attachment_links'] ?? [])
                ->map(function (array $item): array {
                    $url = trim((string) $item['url']);
                    $title = trim((string) ($item['title'] ?? ''));

                    return [
                        'title' => $title !== '' ? $title : (parse_url($url, PHP_URL_HOST) ?: $url),
                        'url' => $url,
                    ];
                })
                ->filter(fn (array $item) => $item['url'] !== '')
                ->values()
                ->all();
        }
        if (($attributes['status'] ?? null) === 'done') {
            $attributes['completed_at'] = now();
        }

        return $attributes;
    }

    private function normalizeAssignmentData(array $data, ?ClubTask $task = null): array
    {
        $replacingSingleAssignee = $task
            && array_key_exists('assigned_to', $data)
            && ! array_key_exists('participant_ids', $data)
            && ($data['assignment_mode'] ?? $this->effectiveAssignmentMode($task)) === 'single';
        $participantIds = collect($replacingSingleAssignee ? [] : ($data['participant_ids'] ?? $task?->participant_ids ?? []))
            ->push(array_key_exists('assigned_to', $data) ? $data['assigned_to'] : $task?->assigned_to)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $mode = $data['assignment_mode'] ?? ($task ? $this->effectiveAssignmentMode($task) : $this->assignmentModeFromData($data));

        abort_if($mode === 'single' && $participantIds->count() > 1, 422, 'Direct assignment supports only one responsible person.');
        $data['assignment_mode'] = $mode;
        $data['participant_ids'] = $participantIds->all();
        $data['assigned_to'] = $mode === 'single' ? $participantIds->first() : null;

        return $data;
    }

    private function assignmentModeFromData(array $data): string
    {
        $ids = $this->participantIdsFromData($data);

        return $ids->count() > 1 || (! ($data['assigned_to'] ?? null) && $ids->isNotEmpty())
            ? 'open_claim'
            : 'single';
    }

    private function effectiveAssignmentMode(ClubTask $task): string
    {
        if (in_array($task->assignment_mode, self::ASSIGNMENT_MODES, true)) {
            return $task->assignment_mode;
        }

        return $this->assignmentModeFromData([
            'participant_ids' => $task->participant_ids,
            'assigned_to' => $task->assigned_to,
        ]);
    }

    private function participantIdsFromData(array $data)
    {
        return collect($data['participant_ids'] ?? [])
            ->push($data['assigned_to'] ?? null)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function taskParticipantIds(ClubTask $task): array
    {
        return $this->participantIdsFromData([
            'participant_ids' => $task->participant_ids,
            'assigned_to' => $task->assigned_to,
        ])->all();
    }

    private function syncTaskStatusFromParticipantProgress(ClubTask $task): void
    {
        $progress = collect($task->participant_progress ?? []);
        $activeIds = collect($this->taskParticipantIds($task))
            ->reject(fn (int $id) => ($task->assignment_status[(string) $id] ?? null) === 'declined');
        $statuses = $activeIds->map(fn (int $id) => $progress[(string) $id] ?? 'open');

        if ($this->effectiveAssignmentMode($task) !== 'shared_all') {
            $task->status = (string) ($statuses->first() ?? 'open');
        } elseif ($statuses->isNotEmpty() && $statuses->every(fn (string $status) => $status === 'done')) {
            $task->status = 'done';
        } elseif ($statuses->contains('in_progress')) {
            $task->status = 'in_progress';
        } elseif ($statuses->contains('waiting')) {
            $task->status = 'waiting';
        } elseif ($statuses->contains('read')) {
            $task->status = 'read';
        } else {
            $task->status = 'open';
        }
        $task->completed_at = $task->status === 'done' ? ($task->completed_at ?? now()) : null;
    }

    private function participantProgressPayload(ClubTask $task, $participants)
    {
        $assignmentStatus = $task->assignment_status ?? [];
        $progress = $task->participant_progress ?? [];

        return $participants->map(fn (array $participant) => [
            ...$participant,
            'status' => $progress[(string) $participant['id']] ?? 'open',
            'assignment_status' => $assignmentStatus[(string) $participant['id']] ?? '',
        ])->values();
    }

    private function activityEntry(string $type, User $actor, array $data = []): array
    {
        return [
            'type' => $type,
            'user' => ['id' => $actor->id, 'name' => $actor->name],
            'data' => $data,
            'created_at' => now()->toJSON(),
        ];
    }

    private function appendActivity(ClubTask $task, string $type, User $actor, array $data = []): void
    {
        $activity = collect($task->activity_log ?? [])
            ->push($this->activityEntry($type, $actor, $data))
            ->take(-100)
            ->values()
            ->all();
        $task->activity_log = $activity;
    }

    private function ensureAssignableUsers(Request $request, Club $club, array $data): void
    {
        $ids = collect($data['participant_ids'] ?? [])
            ->push($data['assigned_to'] ?? null)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        if ($ids->isEmpty()) {
            return;
        }

        $allowed = $this->canManageClub($request, $club)
            ? $this->linkedUserIds($club)
            : $this->memberAssignableIds($club, $request->user());
        abort_unless($ids->diff($allowed)->isEmpty(), 422, 'Selected users must belong to this club and be assignable for you.');
    }

    private function ensureMemberCanSetVisibility(Request $request, Club $club, array $data): void
    {
        if ($this->canManageClub($request, $club) || ! array_key_exists('visibility', $data)) {
            return;
        }

        abort_unless(in_array($data['visibility'], ['personal', 'shared'], true), 422, 'Members can only create personal or shared tasks.');
    }

    private function applyMemberTaskDefaults(Request $request, Club $club, array $data): array
    {
        if ($this->canManageClub($request, $club)) {
            return $data;
        }

        $assigneeIds = collect($data['participant_ids'] ?? [])
            ->push($data['assigned_to'] ?? null)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique();
        $hasOtherAssignee = $assigneeIds->contains(fn (int $id) => $id !== (int) $request->user()->id);
        if (! array_key_exists('visibility', $data) || ! in_array($data['visibility'], ['personal', 'shared'], true)) {
            $data['visibility'] = $hasOtherAssignee ? 'shared' : 'personal';
        }

        return $data;
    }

    private function memberAssignableIds(Club $club, User $user)
    {
        $clubMemberIds = $this->linkedUserIds($club);
        $friendIds = $user->friendships()
            ->pluck('friend_id')
            ->map(fn ($id) => (int) $id);

        return $friendIds
            ->push($user->id)
            ->intersect($clubMemberIds)
            ->values();
    }

    private function clubMemberIds(Club $club)
    {
        return $club->users()->pluck('users.id')
            ->push($club->owner_id)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function linkedUserIds(Club $club)
    {
        $teamUserIds = Team::query()
            ->where('club_id', $club->id)
            ->with('users:id')
            ->get()
            ->flatMap(fn (Team $team) => $team->users->pluck('id'));

        return $this->clubMemberIds($club)
            ->merge($teamUserIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function metaPayload(Club $club): array
    {
        $memberIds = $this->linkedUserIds($club);
        $teamIds = Team::query()->where('club_id', $club->id)->pluck('id');

        return [
            'members' => User::query()
                ->whereIn('id', $memberIds)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
                ->values(),
            'teams' => Team::query()
                ->where('club_id', $club->id)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Team $team) => ['id' => $team->id, 'name' => $team->name])
                ->values(),
            'statuses' => self::STATUSES,
            'priorities' => self::PRIORITIES,
            'visibilities' => self::VISIBILITIES,
            'assignment_modes' => self::ASSIGNMENT_MODES,
            'calendar_events' => Event::query()
                ->where('status', 'scheduled')
                ->whereBetween('start_time', [now()->subYear(), now()->addYear()])
                ->where(function ($query) use ($club, $teamIds) {
                    $query->where('club_id', $club->id)
                        ->when($teamIds->isNotEmpty(), fn ($query) => $query->orWhereIn('team_id', $teamIds));
                })
                ->orderBy('start_time')
                ->limit(500)
                ->get(['id', 'club_id', 'team_id', 'title', 'type', 'start_time', 'end_time', 'location_name', 'location_city'])
                ->map(fn (Event $event) => [
                    'id' => $event->id,
                    'club_id' => $event->club_id,
                    'team_id' => $event->team_id,
                    'title' => $event->title,
                    'type' => $event->type,
                    'start_time' => $event->start_time?->toJSON(),
                    'end_time' => $event->end_time?->toJSON(),
                    'location' => trim(collect([$event->location_name, $event->location_city])->filter()->implode(', ')),
                ])
                ->values(),
        ];
    }

    private function taskPayload(ClubTask $task, Request $request): array
    {
        $participants = User::query()
            ->whereIn('id', $this->taskParticipantIds($task))
            ->get(['id', 'name'])
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
            ->values();

        return [
            'id' => $task->id,
            'club_id' => $task->club_id,
            'club' => $task->club ? ['id' => $task->club->id, 'name' => $task->club->name] : null,
            'team_id' => $task->team_id,
            'team' => $task->team ? ['id' => $task->team->id, 'name' => $task->team->name] : null,
            'created_by' => $task->created_by,
            'creator' => $task->creator ? ['id' => $task->creator->id, 'name' => $task->creator->name] : null,
            'assigned_to' => $task->assigned_to,
            'assignee' => $task->assignee ? ['id' => $task->assignee->id, 'name' => $task->assignee->name] : null,
            'assignment_status' => $task->assignment_status ?? [],
            'assignment_mode' => $this->effectiveAssignmentMode($task),
            'my_assignment_status' => (string) ($task->assignment_status[(string) $request->user()->id] ?? ''),
            'participant_ids' => $task->participant_ids ?? [],
            'participants' => $participants,
            'participant_progress' => $this->participantProgressPayload($task, $participants),
            'my_progress' => (string) ($task->participant_progress[(string) $request->user()->id] ?? ''),
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status,
            'priority' => $task->priority,
            'visibility' => $task->visibility,
            'relationship' => $this->taskRelationship($task, $request->user()),
            'is_creator' => (int) $task->created_by === (int) $request->user()->id,
            'is_assigned_to_me' => (int) $task->assigned_to === (int) $request->user()->id,
            'is_shared_with_me' => in_array((int) $request->user()->id, array_map('intval', $task->participant_ids ?? []), true),
            'requires_response' => ($task->assignment_status[(string) $request->user()->id] ?? null) === 'pending',
            'start_at' => $task->start_at?->format('Y-m-d'),
            'due_at' => $task->due_at?->format('Y-m-d'),
            'checklist' => $task->checklist ?? [],
            'attachment_links' => $task->attachment_links ?? [],
            'comments_count' => (int) ($task->comments_count ?? $task->comments()->count()),
            'attachments_count' => (int) ($task->attachments_count ?? $task->attachments()->count()),
            'comments' => $task->relationLoaded('comments')
                ? $task->comments->sortBy('created_at')->map(fn (ClubTaskComment $comment) => $this->commentPayload($comment))->values()
                : [],
            'attachments' => $task->relationLoaded('attachments')
                ? FileResource::collection($task->attachments)->resolve($request)
                : [],
            'completed_at' => $task->completed_at?->toJSON(),
            'created_at' => $task->created_at?->toJSON(),
            'updated_at' => $task->updated_at?->toJSON(),
            'activity' => $task->activity_log ?? [],
            'can_update_progress' => $this->isAssignedParticipant($task, $request->user())
                && ! in_array($task->assignment_status[(string) $request->user()->id] ?? null, ['pending', 'declined'], true),
            'can_update' => $this->canMutateTask($task, $request->user()),
            'can_delete' => $this->canDeleteTask($request, $task->club, $task),
            'can_leave' => $this->canLeaveTask($task, $request->user()),
        ];
    }

    private function taskRelationship(ClubTask $task, User $user): string
    {
        if ((int) $task->created_by === (int) $user->id) {
            return 'creator';
        }
        if ((int) $task->assigned_to === (int) $user->id) {
            return 'assigned';
        }
        if (in_array((int) $user->id, array_map('intval', $task->participant_ids ?? []), true)) {
            return 'shared';
        }

        return $task->visibility === 'team' ? 'team' : 'club';
    }

    private function notifyNewParticipants(ClubTask $task, Club $club, User $actor, array $previousParticipantIds): void
    {
        collect($this->taskParticipantIds($task))
            ->diff($previousParticipantIds)
            ->reject(fn (int $recipientId) => $recipientId === (int) $actor->id)
            ->each(fn (int $recipientId) => AppNotification::sendLocalized(
                $recipientId,
                'club.task.assigned',
                'organization.notifications.task_assigned_title',
                'organization.notifications.task_assigned_body',
                [
                    'club' => $club->name,
                    'task' => $task->title,
                    'user' => $actor->name,
                ],
                [
                    'club_id' => $club->id,
                    'task_id' => $task->id,
                    'assigned_by' => $actor->id,
                    'url' => '/club-cockpit?panel=tasks&club_id='.$club->id,
                    'mobile_url' => 'airmius://clubs/'.$club->id.'/tasks/'.$task->id,
                    'deep_link' => 'airmius://clubs/'.$club->id.'/tasks/'.$task->id,
                ],
                [
                    'category' => 'club',
                    'priority' => 'normal',
                    'dedupe_key' => 'club-task-assigned:'.$task->id.':'.$recipientId.':'.$task->updated_at?->timestamp,
                ],
            ));
    }

    private function notifyCommentRecipients(ClubTask $task, Club $club, User $actor, ClubTaskComment $comment): void
    {
        collect($task->participant_ids ?? [])
            ->push($task->assigned_to)
            ->push($task->created_by)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn (int $id) => $id === (int) $actor->id)
            ->each(fn (int $recipientId) => AppNotification::sendLocalized(
                $recipientId,
                'club.task.comment',
                'organization.notifications.task_comment_title',
                'organization.notifications.task_comment_body',
                [
                    'club' => $club->name,
                    'task' => $task->title,
                    'user' => $actor->name,
                ],
                [
                    'club_id' => $club->id,
                    'task_id' => $task->id,
                    'comment_id' => $comment->id,
                    'commented_by' => $actor->id,
                    'url' => '/club-cockpit?panel=tasks&club_id='.$club->id,
                    'mobile_url' => 'airmius://clubs/'.$club->id.'/tasks/'.$task->id,
                    'deep_link' => 'airmius://clubs/'.$club->id.'/tasks/'.$task->id,
                ],
                [
                    'category' => 'club',
                    'priority' => 'normal',
                    'dedupe_key' => 'club-task-comment:'.$task->id.':'.$comment->id.':'.$recipientId,
                ],
            ));
    }

    private function notifyProgressRecipients(ClubTask $task, Club $club, User $actor, string $status): void
    {
        if (! in_array($status, ['in_progress', 'waiting', 'done'], true)) {
            return;
        }

        collect($this->taskParticipantIds($task))
            ->push($task->created_by)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn (int $id) => $id === (int) $actor->id)
            ->each(fn (int $recipientId) => AppNotification::sendLocalized(
                $recipientId,
                'club.task.progress',
                'organization.notifications.task_progress_title',
                'organization.notifications.task_progress_body',
                [
                    'club' => $club->name,
                    'task' => $task->title,
                    'user' => $actor->name,
                    'status' => AppNotification::translatedReplacement(
                        'organization.notifications.task_status_'.$status,
                        $status,
                    ),
                ],
                $this->taskNotificationData($task, $club, ['changed_by' => $actor->id]),
                [
                    'category' => 'club',
                    'priority' => $status === 'done' ? 'normal' : 'low',
                    'dedupe_key' => 'club-task-progress:'.$task->id.':'.$recipientId.':'.$status.':'.$task->updated_at?->timestamp,
                ],
            ));
    }

    private function notifyAssignmentResponse(ClubTask $task, Club $club, User $actor, string $response): void
    {
        $creatorId = (int) $task->created_by;
        if (! $creatorId || $creatorId === (int) $actor->id) {
            return;
        }

        AppNotification::sendLocalized(
            $creatorId,
            'club.task.assignment_response',
            'organization.notifications.task_assignment_response_title',
            'organization.notifications.task_assignment_response_body',
            [
                'club' => $club->name,
                'task' => $task->title,
                'user' => $actor->name,
                'response' => AppNotification::translatedReplacement(
                    'organization.notifications.task_response_'.$response,
                    $response,
                ),
            ],
            $this->taskNotificationData($task, $club, ['responded_by' => $actor->id]),
            [
                'category' => 'club',
                'priority' => 'normal',
                'dedupe_key' => 'club-task-assignment-response:'.$task->id.':'.$actor->id.':'.$response,
            ],
        );
    }

    private function taskNotificationData(ClubTask $task, Club $club, array $extra = []): array
    {
        return [
            'club_id' => $club->id,
            'task_id' => $task->id,
            'url' => '/club-cockpit?panel=tasks&club_id='.$club->id,
            'mobile_url' => 'airmius://clubs/'.$club->id.'/tasks/'.$task->id,
            'deep_link' => 'airmius://clubs/'.$club->id.'/tasks/'.$task->id,
            ...$extra,
        ];
    }

    private function commentPayload(ClubTaskComment $comment): array
    {
        return [
            'id' => $comment->id,
            'body' => $comment->body,
            'user' => $comment->user ? ['id' => $comment->user->id, 'name' => $comment->user->name] : null,
            'created_at' => $comment->created_at?->toJSON(),
        ];
    }
}
