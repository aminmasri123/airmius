<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\OrganizationJob;
use App\Support\ClubPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClubJobController extends Controller
{
    public function index(Request $request, Club $club): JsonResponse
    {
        $this->authorizeJobAction($request, $club, ClubPermissions::JOBS_EDIT);

        $jobs = $club->jobs()
            ->with('sport:id,name,slug')
            ->withCount('interests')
            ->latest('id')
            ->get();

        return response()->json([
            'data' => $jobs->map(fn (OrganizationJob $job) => $this->jobPayload($job))->values(),
        ]);
    }

    public function store(Request $request, Club $club): JsonResponse
    {
        $data = $this->validated($request);
        $this->authorizeJobAction($request, $club, ClubPermissions::JOBS_EDIT);
        if ($data['is_published']) {
            $this->authorizeJobAction($request, $club, ClubPermissions::JOBS_PUBLISH);
        }

        $data['created_by'] = $request->user()->id;
        $data['published_at'] = $data['is_published'] ? now() : null;

        $job = $club->jobs()->create($data);
        $job->load('sport:id,name,slug')->loadCount('interests');

        return response()->json([
            'message' => __('recruiting.flash.created'),
            'data' => $this->jobPayload($job),
        ], 201);
    }

    public function update(Request $request, Club $club, OrganizationJob $organizationJob): JsonResponse
    {
        abort_unless((int) $organizationJob->club_id === (int) $club->id, 404);

        $data = $this->validated($request);
        $this->authorizeJobAction($request, $club, ClubPermissions::JOBS_EDIT);
        if ((bool) $data['is_published'] !== (bool) $organizationJob->is_published) {
            $this->authorizeJobAction($request, $club, ClubPermissions::JOBS_PUBLISH);
        }

        $data['published_at'] = $data['is_published']
            ? ($organizationJob->published_at ?? now())
            : null;

        $organizationJob->update($data);
        $organizationJob->load('sport:id,name,slug')->loadCount('interests');

        return response()->json([
            'message' => __('recruiting.flash.updated'),
            'data' => $this->jobPayload($organizationJob),
        ]);
    }

    public function destroy(Request $request, Club $club, OrganizationJob $organizationJob): JsonResponse
    {
        abort_unless((int) $organizationJob->club_id === (int) $club->id, 404);
        $this->authorizeJobAction($request, $club, ClubPermissions::JOBS_DELETE);

        $organizationJob->delete();

        return response()->json(['message' => __('recruiting.flash.deleted')]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['volunteer', 'professional'])],
            'sport_id' => ['nullable', 'integer', 'exists:sports,id'],
            'minimum_experience_level' => ['nullable', Rule::in(OrganizationJob::EXPERIENCE_LEVELS)],
            'required_qualifications' => ['nullable', 'array', 'max:12'],
            'required_qualifications.*' => ['string', 'max:120'],
            'location' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'shift_slots_required' => ['nullable', 'integer', 'min:1', 'max:500'],
            'commitment_type' => ['nullable', Rule::in(OrganizationJob::COMMITMENT_TYPES)],
            'workload' => ['nullable', 'string', 'max:120'],
            'employment_type' => ['nullable', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:5000'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'application_url' => ['nullable', 'url', 'max:2048'],
            'is_published' => ['boolean'],
        ]);

        $data['required_qualifications'] = collect($data['required_qualifications'] ?? [])
            ->map(fn ($qualification) => trim((string) $qualification))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $data['shift_slots_required'] = $data['shift_slots_required'] ?? 1;
        $data['commitment_type'] = $data['commitment_type'] ?? OrganizationJob::COMMITMENT_VOLUNTARY;
        $data['is_published'] = (bool) ($data['is_published'] ?? false);

        return $data;
    }

    private function authorizeJobAction(Request $request, Club $club, string $permission): void
    {
        abort_unless(ClubPermissions::allows($club, $request->user(), $permission), 403);
    }

    private function jobPayload(OrganizationJob $job): array
    {
        return [
            'id' => $job->id,
            'club_id' => $job->club_id,
            'sport_id' => $job->sport_id,
            'sport' => $job->sport,
            'title' => $job->title,
            'type' => $job->type,
            'minimum_experience_level' => $job->minimum_experience_level,
            'required_qualifications' => $job->required_qualifications ?? [],
            'location' => $job->location,
            'starts_at' => $job->starts_at?->toJSON(),
            'ends_at' => $job->ends_at?->toJSON(),
            'shift_slots_required' => $job->shift_slots_required,
            'commitment_type' => $job->commitment_type,
            'workload' => $job->workload,
            'employment_type' => $job->employment_type,
            'description' => $job->description,
            'contact_email' => $job->contact_email,
            'application_url' => $job->application_url,
            'is_published' => (bool) $job->is_published,
            'published_at' => $job->published_at?->toJSON(),
            'interests_count' => (int) ($job->interests_count ?? 0),
            'created_at' => $job->created_at?->toJSON(),
            'updated_at' => $job->updated_at?->toJSON(),
        ];
    }
}
