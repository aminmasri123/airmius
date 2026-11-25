<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Club;
use App\Models\User;
use App\Models\WorkAutomationJob;
use App\Services\WorkAutomationJobService;
use App\Support\ClubPermissions;
use App\Support\WorkAutomationAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkAutomationJobController extends Controller
{
    public function clubs(Request $request)
    {
        return response()->json(['data' => WorkAutomationAccess::clubs($request->user())])
            ->header('Cache-Control', 'private, no-store');
    }

    public function index(Request $request, Club $club)
    {
        $this->authorizeAccess($club, $request);

        $data = $request->validate([
            'status' => ['nullable', Rule::in(['scheduled', 'queued', 'running', 'completed', 'failed'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $status = $data['status'] ?? null;
        $canCreate = WorkAutomationAccess::canCreate($club, $request->user());
        $jobs = WorkAutomationAccess::visibleJobs(WorkAutomationJob::query(), $club, $request->user())
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest('id')->paginate(25);

        return response()->json([
            'data' => $jobs->getCollection()->map(fn (WorkAutomationJob $job) => $this->payload($job, $request->user())),
            'meta' => [
                'current_page' => $jobs->currentPage(), 'last_page' => $jobs->lastPage(), 'total' => $jobs->total(),
                'can_create' => $canCreate,
                'recipient_permissions' => collect(ClubPermissions::ALL)->map(fn ($key) => [
                    'key' => $key, 'label' => ClubPermissions::LABELS[$key] ?? $key,
                ]),
                'recipient_users' => $canCreate ? $this->recipientUsers($club) : [],
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function recipients(Request $request, Club $club, WorkAutomationJobService $service)
    {
        abort_unless(WorkAutomationAccess::canCreate($club, $request->user()), 403);
        $data = $request->validate([
            'recipient_roles' => ['nullable', 'array', 'max:8'],
            'recipient_roles.*' => ['string', Rule::in(ClubPermissions::ALL), 'distinct'],
            'recipient_user_ids' => ['nullable', 'array', 'max:500'],
            'recipient_user_ids.*' => ['integer', 'distinct', Rule::exists('club_user', 'user_id')->where('club_id', $club->id)
                ->where(fn ($query) => $query->whereNull('membership_status')->orWhere('membership_status', 'active'))],
            'include_creator' => ['nullable', 'boolean'],
        ]);

        return response()->json(['data' => ['count' => $service->recipients(
            $club,
            $data['recipient_roles'] ?? [],
            $data['recipient_user_ids'] ?? [],
            $request->boolean('include_creator') ? $request->user()->id : null,
        )->count()]])
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, Club $club, WorkAutomationJobService $service)
    {
        abort_unless(WorkAutomationAccess::canCreate($club, $request->user()), 403);

        $data = $request->validate([
            'kind' => ['required', Rule::in([WorkAutomationJob::KIND_REMINDER, WorkAutomationJob::KIND_ESCALATION])],
            'idempotency_key' => ['required', 'string', 'max:160'],
            'recipient_roles' => ['nullable', 'array', 'max:8'],
            'recipient_roles.*' => ['string', Rule::in(ClubPermissions::ALL), 'distinct'],
            'recipient_user_ids' => ['nullable', 'array', 'max:500'],
            'recipient_user_ids.*' => ['integer', 'distinct', Rule::exists('club_user', 'user_id')->where('club_id', $club->id)
                ->where(fn ($query) => $query->whereNull('membership_status')->orWhere('membership_status', 'active'))],
            'include_creator' => ['nullable', 'boolean'],
            'scheduled_at' => ['nullable', 'date'],
            'payload' => ['nullable', 'array:template,title,body'],
            'payload.template' => ['sometimes', Rule::in(['due_task', 'reminder', 'escalation'])],
            'payload.title' => ['sometimes', 'required', 'string', 'max:160'],
            'payload.body' => ['sometimes', 'required', 'string', 'max:2000'],
        ]);

        if (isset($data['scheduled_at']) && Carbon::parse($data['scheduled_at'])->isPast()
            && ! WorkAutomationJob::query()->where('club_id', $club->id)->where('idempotency_key', $data['idempotency_key'])->exists()) {
            throw ValidationException::withMessages(['scheduled_at' => 'Choose a future execution time.']);
        }

        $payload = $data['payload'] ?? [];
        $payload['_recipient_user_ids'] = array_values(array_unique($data['recipient_user_ids'] ?? []));
        $payload['_include_creator'] = (bool) ($data['include_creator'] ?? false);
        $job = $service->enqueue(
            $club,
            $data['kind'],
            $data['idempotency_key'],
            $request->user(),
            null,
            $data['recipient_roles'] ?? [],
            $payload,
            isset($data['scheduled_at']) ? Carbon::parse($data['scheduled_at'])->utc() : null
        );

        return response()->json(['data' => $this->payload($job, $request->user())], $job->wasRecentlyCreated ? 202 : 200)
            ->header('Cache-Control', 'private, no-store');
    }

    public function retry(Request $request, Club $club, WorkAutomationJob $workAutomationJob, WorkAutomationJobService $service)
    {
        $this->authorizeAccess($club, $request);
        abort_unless((int) $workAutomationJob->club_id === (int) $club->id, 404);
        abort_unless(WorkAutomationAccess::canRetry($workAutomationJob, $request->user()), 403);
        $data = $request->validate(['expected_attempts' => ['nullable', 'integer', 'min:0']]);

        return response()->json(['data' => $this->payload($service->retry($workAutomationJob, $request->user(), $data['expected_attempts'] ?? null), $request->user())])
            ->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, Club $club, WorkAutomationJob $workAutomationJob, WorkAutomationJobService $service)
    {
        abort_unless((int) $workAutomationJob->club_id === (int) $club->id, 404);
        abort_unless(WorkAutomationAccess::canManageScheduled($workAutomationJob, $request->user()), 409);
        $data = $request->validate([
            'kind' => ['required', Rule::in([WorkAutomationJob::KIND_REMINDER, WorkAutomationJob::KIND_ESCALATION])],
            'recipient_roles' => ['nullable', 'array', 'max:8'],
            'recipient_roles.*' => ['string', Rule::in(ClubPermissions::ALL), 'distinct'],
            'recipient_user_ids' => ['nullable', 'array', 'max:500'],
            'recipient_user_ids.*' => ['integer', 'distinct', Rule::exists('club_user', 'user_id')->where('club_id', $club->id)
                ->where(fn ($query) => $query->whereNull('membership_status')->orWhere('membership_status', 'active'))],
            'include_creator' => ['nullable', 'boolean'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'payload' => ['required', 'array:title,body'],
            'payload.title' => ['required', 'string', 'max:160'],
            'payload.body' => ['required', 'string', 'max:2000'],
        ]);

        $data['payload']['_recipient_user_ids'] = array_values(array_unique($data['recipient_user_ids'] ?? []));
        $data['payload']['_include_creator'] = (bool) ($data['include_creator'] ?? false);

        return response()->json(['data' => $this->payload($service->updateScheduled($workAutomationJob, $request->user(), $data), $request->user())])
            ->header('Cache-Control', 'private, no-store');
    }

    public function destroy(Request $request, Club $club, WorkAutomationJob $workAutomationJob, WorkAutomationJobService $service)
    {
        abort_unless((int) $workAutomationJob->club_id === (int) $club->id, 404);
        abort_unless(WorkAutomationAccess::canDelete($workAutomationJob, $request->user()), 409);
        $service->deleteJob($workAutomationJob, $request->user());

        return response()->noContent();
    }

    private function authorizeAccess(Club $club, Request $request): void
    {
        abort_unless(WorkAutomationAccess::canView($club, $request->user()), 403);
    }

    public function show(Request $request, Club $club, WorkAutomationJob $workAutomationJob)
    {
        $this->authorizeAccess($club, $request);
        $job = WorkAutomationAccess::visibleJobs(WorkAutomationJob::query(), $club, $request->user())
            ->whereKey($workAutomationJob->id)->firstOrFail();

        return response()->json(['data' => [
            ...$this->payload($job, $request->user()),
            'history' => Activity::query()->where('club_id', $club->id)
                ->where('subject_type', WorkAutomationJob::class)->where('subject_id', $job->id)
                ->oldest('id')->get()->map(fn (Activity $activity) => [
                    'event' => $activity->type, 'at' => $activity->created_at->toIso8601String(), 'data' => $activity->data,
                ]),
        ]])->header('Cache-Control', 'private, no-store');
    }

    private function payload(WorkAutomationJob $job, User $user): array
    {
        return [
            'id' => $job->id,
            'club_id' => $job->club_id,
            'kind' => $job->kind,
            'status' => $job->status,
            'idempotency_key' => $job->idempotency_key,
            'recipient_roles' => $job->recipient_roles ?? [],
            'recipient_user_ids' => $job->payload['_recipient_user_ids'] ?? [],
            'include_creator' => (bool) ($job->payload['_include_creator'] ?? false),
            'attempts' => $job->attempts,
            'queued_at' => $job->queued_at?->toIso8601String(),
            'started_at' => $job->started_at?->toIso8601String(),
            'completed_at' => $job->completed_at?->toIso8601String(),
            'failed_at' => $job->failed_at?->toIso8601String(),
            'retry_queued_at' => $job->retry_queued_at?->toIso8601String(),
            'error_code' => $job->error_code,
            'title' => $job->payload['title'] ?? null,
            'body' => $job->payload['body'] ?? null,
            'template' => $job->payload['template'] ?? null,
            'scheduled_at' => $job->scheduled_at?->toIso8601String(),
            'result' => $job->result,
            'can_retry' => $job->status === WorkAutomationJob::STATUS_FAILED && WorkAutomationAccess::canRetry($job, $user),
            'can_edit' => WorkAutomationAccess::canManageScheduled($job, $user),
            'can_delete' => WorkAutomationAccess::canDelete($job, $user),
        ];
    }

    private function recipientUsers(Club $club): array
    {
        $users = $club->users()
            ->where(fn ($query) => $query->whereNull('club_user.membership_status')
                ->orWhere('club_user.membership_status', 'active'))
            ->get(['users.id', 'users.name']);
        if ($club->owner && ! $users->contains('id', $club->owner_id)) {
            $users->push($club->owner);
        }

        return $users->map(fn ($user) => ['id' => (int) data_get($user, 'id'), 'name' => (string) data_get($user, 'name')])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
    }
}
