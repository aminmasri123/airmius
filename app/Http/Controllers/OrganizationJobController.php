<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\OrganizationJob;
use App\Models\Sport;
use App\Services\OrganizationJobDirectoryService;
use App\Services\OrganizationJobInterestService;
use App\Support\ClubPermissions;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class OrganizationJobController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request, Club $club)
    {
        $data = $this->validated($request);
        $this->authorizeJobAction($request, $club, ClubPermissions::JOBS_EDIT);
        if ($data['is_published']) {
            $this->authorizeJobAction($request, $club, ClubPermissions::JOBS_PUBLISH);
        }
        $data['created_by'] = $request->user()->id;
        $data['published_at'] = $data['is_published'] ? now() : null;

        $club->jobs()->create($data);

        return back()->with('success', __('recruiting.flash.created'));
    }

    public function update(Request $request, OrganizationJob $organizationJob)
    {
        $data = $this->validated($request);
        $this->authorizeJobAction($request, $organizationJob->club, ClubPermissions::JOBS_EDIT);
        if ((bool) $data['is_published'] !== (bool) $organizationJob->is_published) {
            $this->authorizeJobAction($request, $organizationJob->club, ClubPermissions::JOBS_PUBLISH);
        }
        $data['published_at'] = $data['is_published']
            ? ($organizationJob->published_at ?? now())
            : null;

        $organizationJob->update($data);

        return back()->with('success', __('recruiting.flash.updated'));
    }

    public function destroy(Request $request, OrganizationJob $organizationJob)
    {
        $this->authorizeJobAction($request, $organizationJob->club, ClubPermissions::JOBS_DELETE);

        $organizationJob->delete();

        return back()->with('success', __('recruiting.flash.deleted'));
    }

    public function publicIndex(Request $request, OrganizationJobDirectoryService $directory)
    {
        $filters = $directory->filters($request->only(['sport_type', 'address', 'type', 'sort']));

        return Inertia::render('Guest/Jobs', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'jobs' => fn () => $directory->paginate($filters)->withQueryString(),
            'filters' => $filters,
            'sports' => fn () => Sport::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'category']),
        ]);
    }

    public function submitInterest(
        Request $request,
        OrganizationJob $organizationJob,
        OrganizationJobInterestService $interests,
    ) {
        abort_unless($organizationJob->is_published, 404);

        $data = $request->validate(OrganizationJobInterestService::rules());
        $interests->submit(
            $organizationJob,
            $data,
            $request->user(),
            $request->ip(),
            $request->userAgent(),
            app()->getLocale(),
        );

        return back()->with('success', __('recruiting.flash.interest_sent'));
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

        return $data;
    }

    private function authorizeJobAction(Request $request, Club $club, string $permission): void
    {
        abort_unless(
            ClubPermissions::allows($club, $request->user(), $permission),
            403,
        );
    }
}
