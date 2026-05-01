<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\OrganizationJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class OrganizationJobController extends Controller
{
    public function store(Request $request, Club $club)
    {
        $this->authorize('update', $club);

        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $data['published_at'] = $data['is_published'] ? now() : null;

        $club->jobs()->create($data);

        return back()->with('success', 'Stelle erstellt.');
    }

    public function update(Request $request, OrganizationJob $organizationJob)
    {
        $this->authorize('update', $organizationJob->club);

        $data = $this->validated($request);
        $data['published_at'] = $data['is_published']
            ? ($organizationJob->published_at ?? now())
            : null;

        $organizationJob->update($data);

        return back()->with('success', 'Stelle aktualisiert.');
    }

    public function destroy(Request $request, OrganizationJob $organizationJob)
    {
        $this->authorize('update', $organizationJob->club);

        $organizationJob->delete();

        return back()->with('success', 'Stelle geloescht.');
    }

    public function publicIndex()
    {
        return Inertia::render('Guest/Jobs', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'jobs' => OrganizationJob::query()
                ->published()
                ->with('club:id,name,logo')
                ->latest('published_at')
                ->get(),
        ]);
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
}
