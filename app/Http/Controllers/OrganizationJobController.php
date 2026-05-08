<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\OrganizationJob;
use App\Models\OrganizationJobInterest;
use App\Models\Sport;
use App\Notifications\OrganizationJobInterestReceived;
use App\Support\ClubRoles;
use App\Support\Roles;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
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

        return back()->with('success', 'Stelle erstellt.');
    }

    public function update(Request $request, OrganizationJob $organizationJob)
    {
        $this->authorizeManageJobs($request, $organizationJob->club);

        $data = $this->validated($request);
        $data['published_at'] = $data['is_published']
            ? ($organizationJob->published_at ?? now())
            : null;

        $organizationJob->update($data);

        return back()->with('success', 'Stelle aktualisiert.');
    }

    public function destroy(Request $request, OrganizationJob $organizationJob)
    {
        $this->authorizeManageJobs($request, $organizationJob->club);

        $organizationJob->delete();

        return back()->with('success', 'Stelle gelöscht.');
    }

    public function publicIndex(Request $request)
    {
        $filters = $request->only(['sport_type', 'address']);

        return Inertia::render('Guest/Jobs', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'jobs' => OrganizationJob::query()
                ->published()
                ->with('club:id,name,logo,sport_type,country,street,house_number,postal_code,city,state')
                ->when($filters['sport_type'] ?? null, fn ($query, $sport) => $query
                    ->whereHas('club', fn ($clubQuery) => $clubQuery->where('sport_type', $sport)))
                ->when($filters['address'] ?? null, fn ($query, $address) => $query->where(function ($query) use ($address) {
                    $query->where('location', 'like', "%{$address}%")
                        ->orWhereHas('club', fn ($clubQuery) => $clubQuery
                            ->where('city', 'like', "%{$address}%")
                            ->orWhere('postal_code', 'like', "%{$address}%")
                            ->orWhere('street', 'like', "%{$address}%")
                            ->orWhere('state', 'like', "%{$address}%")
                            ->orWhere('country', 'like', "%{$address}%"));
                }))
                ->latest('published_at')
                ->get(),
            'filters' => $filters,
            'sports' => Sport::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'category']),
        ]);
    }

    public function submitInterest(Request $request, OrganizationJob $organizationJob)
    {
        abort_unless($organizationJob->is_published, 404);

        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $interest = $organizationJob->interests()->create([
            ...$data,
            'user_id' => $user?->id,
            'ip_address' => $request->ip(),
            'user_agent' => (string) str($request->userAgent() ?? '')->limit(512, ''),
        ]);

        $interest->load('job.club.owner', 'job.club.admins');

        $recipients = $organizationJob->club->admins
            ->push($organizationJob->club->owner)
            ->filter()
            ->unique('id')
            ->values();

        try {
            if ($organizationJob->contact_email) {
                Notification::route('mail', $organizationJob->contact_email)
                    ->notify(new OrganizationJobInterestReceived($interest));
            }

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new OrganizationJobInterestReceived($interest));
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        return back()->with('success', 'Dein Interesse wurde gesendet.');
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
