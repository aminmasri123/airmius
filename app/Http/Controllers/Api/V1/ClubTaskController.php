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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ClubTaskController extends Controller
{
    private const STATUSES = ['open', 'in_progress', 'waiting', 'done'];
    private const PRIORITIES = ['low', 'normal', 'high', 'urgent'];
    private const VISIBILITIES = ['personal', 'shared', 'team', 'club'];

    public function __construct(private FileService $files) {}

    public function index(Request $request, Club $club)
    {
        $this->authorizeTaskAccess($request, $club);
        $manager = $this->canManageClub($request, $club);
        $user = $request->user();
        $teamIds = $this->clubTeamIdsFor($club, $user);

        $tasks = ClubTask::query()
            ->where('club_id', $club->id)
            ->when(! $manager, fn ($query) => $query->where(fn ($visible) => $this->visibleTaskQuery($visible, $user, $teamIds)))
            ->with(['creator:id,name,profile_photo_path', 'assignee:id,name,profile_photo_path', 'team:id,name', 'comments.user:id,name,profile_photo_path', 'attachments'])
            ->withCount(['comments', 'attachments'])
            ->orderByRaw('completed_at IS NOT NULL')
            ->orderByRaw('due_at IS NULL')
            ->orderBy('due_at')
            ->orderByDesc('id')
            ->get()
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
        $task = ClubTask::create([
            'club_id' => $club->id,
            'created_by' => $request->user()->id,
            ...$this->taskAttributes($data),
        ])->load(['creator:id,name,profile_photo_path', 'assignee:id,name,profile_photo_path', 'team:id,name', 'comments.user:id,name,profile_photo_path', 'attachments'])
            ->loadCount(['comments', 'attachments']);

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
            'checklist' => ['nullable', 'array', 'max:50'],
            'checklist.*.title' => ['required_with:checklist', 'string', 'max:255'],
            'checklist.*.done' => ['nullable', 'boolean'],
            'start_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
            'completed' => ['sometimes', 'required', 'boolean'],
            'updated_at' => ['nullable', 'date'],
        ]);

        $this->ensureAssignableUsers($club, $data);
        $this->ensureNotStale($task, $data['updated_at'] ?? null);
        $task->fill($this->taskAttributes($data, false));
        if (array_key_exists('completed', $data)) {
            $task->status = $data['completed'] ? 'done' : ($task->status === 'done' ? 'open' : $task->status);
            $task->completed_at = $data['completed'] ? now() : null;
        } elseif (array_key_exists('status', $data)) {
            $task->completed_at = $data['status'] === 'done' ? ($task->completed_at ?? now()) : null;
        }
        $task->save();
        $task->load(['creator:id,name,profile_photo_path', 'assignee:id,name,profile_photo_path', 'team:id,name', 'comments.user:id,name,profile_photo_path', 'attachments'])
            ->loadCount(['comments', 'attachments']);

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

    public function comment(Request $request, Club $club, ClubTask $task)
    {
        $this->authorizeTaskAccess($request, $club, $task, 'comment');
        abort_unless((int) $task->club_id === (int) $club->id, 404);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $comment = $task->comments()->create([
            'user_id' => $request->user()->id,
            'body' => trim($data['body']),
        ])->load('user:id,name,profile_photo_path');

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

        return response()->json(['data' => $uploaded], 201);
    }

    public function detach(Request $request, Club $club, ClubTask $task, int $file)
    {
        $this->authorizeTaskAccess($request, $club, $task, 'update');
        abort_unless((int) $task->club_id === (int) $club->id, 404);
        $task->attachments()->detach($file);

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
            'checklist' => ['nullable', 'array', 'max:50'],
            'checklist.*.title' => ['required_with:checklist', 'string', 'max:255'],
            'checklist.*.done' => ['nullable', 'boolean'],
            'start_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
        ]);
        $this->ensureAssignableUsers($club, $data);

        return $data;
    }

    private function authorizeTaskAccess(Request $request, Club $club, ?ClubTask $task = null, string $ability = 'read'): void
    {
        $user = $request->user();
        $canUseClubTasks = $this->canManageClub($request, $club)
            || (int) $club->owner_id === (int) $user->id
            || $club->users()
                ->where('users.id', $user->id)
                ->wherePivot('membership_status', 'active')
                ->exists();

        abort_unless($canUseClubTasks, 403);

        if (! $task || $this->canManageClub($request, $club)) {
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

    private function canViewTask(Club $club, ClubTask $task, User $user): bool
    {
        if ($this->canMutateTask($task, $user)) {
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
        return (int) $task->created_by === (int) $user->id
            || (int) $task->assigned_to === (int) $user->id
            || in_array((int) $user->id, array_map('intval', $task->participant_ids ?? []), true);
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

        $clientTimestamp = \Illuminate\Support\Carbon::parse($updatedAt)->utc();
        $serverTimestamp = $task->updated_at?->utc();
        abort_if($serverTimestamp && $clientTimestamp->lt($serverTimestamp), 409, 'Task was changed by someone else. Please reload and try again.');
    }

    private function taskAttributes(array $data, bool $withDefaults = true): array
    {
        $attributes = [];
        foreach (['title', 'description', 'status', 'priority', 'visibility', 'team_id', 'assigned_to', 'start_at', 'due_at'] as $key) {
            if (array_key_exists($key, $data)) {
                $attributes[$key] = is_string($data[$key]) ? trim($data[$key]) : $data[$key];
            }
        }
        if ($withDefaults) {
            $attributes['status'] ??= 'open';
            $attributes['priority'] ??= 'normal';
            $attributes['visibility'] ??= 'club';
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
        if (($attributes['status'] ?? null) === 'done') {
            $attributes['completed_at'] = now();
        }

        return $attributes;
    }

    private function ensureAssignableUsers(Club $club, array $data): void
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

        $allowed = $this->clubMemberIds($club);
        abort_unless($ids->diff($allowed)->isEmpty(), 422, 'Selected users must belong to this club.');
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

    private function metaPayload(Club $club): array
    {
        $memberIds = $this->clubMemberIds($club);
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
            ->whereIn('id', $task->participant_ids ?? [])
            ->get(['id', 'name'])
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
            ->values();

        return [
            'id' => $task->id,
            'club_id' => $task->club_id,
            'team_id' => $task->team_id,
            'team' => $task->team ? ['id' => $task->team->id, 'name' => $task->team->name] : null,
            'created_by' => $task->created_by,
            'creator' => $task->creator ? ['id' => $task->creator->id, 'name' => $task->creator->name] : null,
            'assigned_to' => $task->assigned_to,
            'assignee' => $task->assignee ? ['id' => $task->assignee->id, 'name' => $task->assignee->name] : null,
            'participant_ids' => $task->participant_ids ?? [],
            'participants' => $participants,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status,
            'priority' => $task->priority,
            'visibility' => $task->visibility,
            'start_at' => $task->start_at?->format('Y-m-d'),
            'due_at' => $task->due_at?->format('Y-m-d'),
            'checklist' => $task->checklist ?? [],
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
