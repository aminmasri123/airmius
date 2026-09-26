<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\WorkAutomationJob;
use App\Services\WorkAutomationJobService;
use App\Support\ClubPermissions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkAutomationJobController extends Controller
{
    public function index(Request $request, Club $club)
    {
        $this->authorizeAccess($club, $request);

        $status = $request->query('status');

        return response()->json([
            'data' => WorkAutomationJob::query()
                ->where('club_id', $club->id)
                ->when($status, fn ($query) => $query->where('status', $status))
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn (WorkAutomationJob $job) => $this->payload($job))
                ->values(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, Club $club, WorkAutomationJobService $service)
    {
        $this->authorizeAccess($club, $request);

        $data = $request->validate([
            'kind' => ['required', Rule::in([WorkAutomationJob::KIND_REMINDER, WorkAutomationJob::KIND_ESCALATION])],
            'idempotency_key' => ['required', 'string', 'max:160'],
            'recipient_roles' => ['nullable', 'array', 'max:8'],
            'recipient_roles.*' => ['string', 'max:80'],
            'payload' => ['nullable', 'array'],
        ]);

        $job = $service->enqueue(
            $club,
            $data['kind'],
            $data['idempotency_key'],
            $request->user(),
            null,
            $data['recipient_roles'] ?? [],
            $data['payload'] ?? []
        );

        return response()->json(['data' => $this->payload($job)], $job->wasRecentlyCreated ? 202 : 200)
            ->header('Cache-Control', 'private, no-store');
    }

    public function retry(Request $request, Club $club, WorkAutomationJob $workAutomationJob, WorkAutomationJobService $service)
    {
        $this->authorizeAccess($club, $request);
        abort_unless((int) $workAutomationJob->club_id === (int) $club->id, 404);

        return response()->json(['data' => $this->payload($service->retry($workAutomationJob, $request->user()))])
            ->header('Cache-Control', 'private, no-store');
    }

    private function authorizeAccess(Club $club, Request $request): void
    {
        abort_unless(
            ClubPermissions::allows($club, $request->user(), ClubPermissions::FINANCE_MANAGE)
                || ClubPermissions::allows($club, $request->user(), ClubPermissions::MEMBERS_MANAGE),
            403
        );
    }

    private function payload(WorkAutomationJob $job): array
    {
        return [
            'id' => $job->id,
            'club_id' => $job->club_id,
            'kind' => $job->kind,
            'status' => $job->status,
            'idempotency_key' => $job->idempotency_key,
            'recipient_roles' => $job->recipient_roles ?? [],
            'attempts' => $job->attempts,
            'queued_at' => $job->queued_at?->toIso8601String(),
            'started_at' => $job->started_at?->toIso8601String(),
            'completed_at' => $job->completed_at?->toIso8601String(),
            'failed_at' => $job->failed_at?->toIso8601String(),
            'retry_queued_at' => $job->retry_queued_at?->toIso8601String(),
            'error_code' => $job->error_code,
        ];
    }
}
