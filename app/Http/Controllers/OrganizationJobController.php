<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\OrganizationJob;
use App\Models\Sport;
use App\Services\OrganizationJobDirectoryService;
use App\Services\OrganizationJobInterestService;
use App\Support\ClubRoles;
use App\Support\Roles;
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
        $this->authorizeManageJobs($request, $club);

        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $data['published_at'] = $data['is_published'] ? now() : null;

        $club->jobs()->create($data);

        return back()->with('success', __('recruiting.flash.created'));
    }

    public function update(Request $request, OrganizationJob $organizationJob)
    {
        $this->authorizeManageJobs($request, $organizationJob->club);

        $data = $this->validated($request);
        $data['published_at'] = $data['is_published']
            ? ($organizationJob->published_at ?? now())
            : null;

        $organizationJob->update($data);

        return back()->with('success', __('recruiting.flash.updated'));
    }

    public function destroy(Request $request, OrganizationJob $organizationJob)
    {
        $this->authorizeManageJobs($request, $organizationJob->club);

        $organizationJob->delete();

        return back()->with('success', __('recruiting.flash.deleted'));
    }

    public function publicIndex(Request $request, OrganizationJobDirectoryService $directory)
    {
        $filters = $directory->filters($request->only(['sport_type', 'address', 'type', 'sort']));
        $jobs = $directory->paginate($filters);

        return Inertia::render('Guest/Jobs', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'jobs' => $jobs->withQueryString(),
            'filters' => $filters,
            'sports' => Sport::query()
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
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['volunteer', 'professional'])],
            'location' => ['nullable', 'string', 'max:255'],
            'workload' => ['nullable', 'string', 'max:120'],
            'employment_type' => ['nullable', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:5000'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'application_url' => ['nullable', 'url', 'max:2048'],
            'is_published' => ['boolean'],
        ]);
    }

    private function authorizeManageJobs(Request $request, Club $club): void
    {
        $user = $request->user();

        abort_unless(
            $user?->hasAnyRole(Roles::FULL_ACCESS)
                || (
                    $user?->can('club.jobs.manage')
                    && (
                        $club->owner_id === $user->id
                        || tap($club->users()->where('users.id', $user->id), fn ($query) => ClubRoles::whereAny($query, ['owner', 'admin', 'manager']))->exists()
                    )
                ),
            403
        );
    }
}
