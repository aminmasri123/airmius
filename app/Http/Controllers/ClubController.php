<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Team;
use App\Services\ClubService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ClubController extends Controller
{
    public function __construct(private ClubService $service) {}

    public function index()
    {
        $this->authorize('viewAny', Team::class);

        $clubs = Club::query()
            ->visibleTo(auth()->user())
            ->with('teams')
            ->get();

        return Inertia::render('Auth/Dashboard/Teams/Index', [
            'clubs' => $clubs,
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Club::class);

        $club = $this->service->create(auth()->user(), $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]));

        session(['club_id' => $club->id]);

        return back()->with('success', 'Club erstellt');
    }

    public function destroy(Club $club)
    {
        $this->authorize('delete', $club);

        $this->service->delete($club);

        return back();
    }
}
