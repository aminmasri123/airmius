<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OrganizationJob;
use App\Services\OrganizationJobDirectoryService;
use App\Services\OrganizationJobInterestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PublicRecruitingController extends Controller
{
    public function index(Request $request, OrganizationJobDirectoryService $directory): JsonResponse
    {
        $input = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', Rule::in(['professional', 'volunteer'])],
            'sport_type' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:160'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $filters = $directory->filters($input);
        $jobs = $directory->paginate($filters, min(max((int) ($input['per_page'] ?? 20), 1), 50));

        return response()->json([
            'data' => collect($jobs->items())
                ->map(fn (OrganizationJob $job) => $directory->publicCard($job))
                ->values(),
            'filters' => $filters,
            'meta' => [
                'current_page' => $jobs->currentPage(),
                'last_page' => $jobs->lastPage(),
                'per_page' => $jobs->perPage(),
                'total' => $jobs->total(),
            ],
        ]);
    }

    public function submitInterest(
        Request $request,
        OrganizationJob $organizationJob,
        OrganizationJobInterestService $interests,
    ): JsonResponse {
        abort_unless($organizationJob->is_published, 404);
        $data = $request->validate(OrganizationJobInterestService::rules());
        $user = $request->user() ?: auth('sanctum')->user();
        $interests->submit(
            $organizationJob,
            $data,
            $user,
            $request->ip(),
            $request->userAgent(),
            app()->getLocale(),
        );

        return response()->json([
            'message' => __('recruiting.flash.interest_sent'),
            'data' => ['job_id' => $organizationJob->id],
        ], 201);
    }
}
