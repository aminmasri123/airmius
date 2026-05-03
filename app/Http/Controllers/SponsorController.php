<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Sponsor;
use App\Services\PlanFeatureService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SponsorController extends Controller
{
    public function __construct(private PlanFeatureService $planFeatures) {}

    public function index(Request $request)
    {
        abort_unless($request->user()->can('org.manage'), 403);

        return Inertia::render('Auth/Dashboard/Sponsors/Index', [
            'sponsors' => Sponsor::query()
                ->with('club:id,name')
                ->latest('id')
                ->paginate(25),
            'clubs' => Club::query()
                ->visibleTo($request->user())
                ->with('currentSubscription.plan')
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get()
                ->map(fn (Club $club) => [
                    'id' => $club->id,
                    'name' => $club->name,
                    'capabilities' => $this->planFeatures->capabilities($club),
                ]),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('org.manage'), 403);

        $data = $this->validated($request);
        $club = Club::query()->visibleTo($request->user())->findOrFail($data['club_id']);
        $this->planFeatures->ensureAllows($club, 'sponsors');

        Sponsor::create($data);

        return back()->with('success', 'Sponsor erstellt.');
    }

    public function update(Request $request, Sponsor $sponsor)
    {
        abort_unless($request->user()->can('org.manage'), 403);
        $this->planFeatures->ensureAllows($sponsor->club, 'sponsors');

        $sponsor->update($this->validated($request));

        return back()->with('success', 'Sponsor aktualisiert.');
    }

    public function destroy(Request $request, Sponsor $sponsor)
    {
        abort_unless($request->user()->can('org.manage'), 403);

        $sponsor->delete();

        return back()->with('success', 'Sponsor gelöscht.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'club_id' => ['required', 'exists:clubs,id'],
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'logo' => ['nullable', 'string', 'max:2048'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);
    }
}
