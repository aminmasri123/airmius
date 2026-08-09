<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OrganizationJobInterest;
use App\Services\RecruitingPipelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RecruitingPipelineController extends Controller
{
    public function __construct(private RecruitingPipelineService $pipeline) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(RecruitingPipelineService::STATUSES)],
            'job_id' => ['nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json([
            'data' => $this->pipeline->payload(
                $request->user(),
                $filters,
                (int) ($filters['per_page'] ?? 25),
            ),
        ]);
    }

    public function update(Request $request, OrganizationJobInterest $interest): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(RecruitingPipelineService::STATUSES)],
            'internal_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $updated = $this->pipeline->update($request->user(), $interest, $data);

        return response()->json([
            'message' => __('recruiting.pipeline.updated'),
            'data' => ['id' => $updated->id, 'status' => $updated->status],
        ]);
    }

    public function destroy(Request $request, OrganizationJobInterest $interest): JsonResponse
    {
        $this->pipeline->erase($request->user(), $interest);

        return response()->json(['message' => __('recruiting.pipeline.erased')]);
    }

    public function chat(Request $request, OrganizationJobInterest $interest): JsonResponse
    {
        $conversation = $this->pipeline->openConversation($request->user(), $interest);

        return response()->json([
            'data' => [
                'conversation_id' => $conversation->id,
                'name' => $conversation->name,
            ],
        ]);
    }
}
