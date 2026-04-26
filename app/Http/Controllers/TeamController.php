<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Team;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TeamController extends Controller
{
    use AuthorizesRequests;

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

        dd($clubs);

        return Inertia::render('Auth/Dashboard/Teams/Index', [
            'clubs' => $clubs,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Team::class);

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        Team::create([
            'name' => $request->name,
            'club_id' => currentClub()->id,
        ]);

        return back()->with('success', 'Team erstellt');
    }

    public function update(Request $request, Team $team)
    {
        $this->authorize('update', $team);

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $team->update([
            'name' => $request->name,
        ]);

        return back()->with('success', 'Team aktualisiert');
    }

    public function destroy(Team $team)
    {
        $this->authorize('delete', $team);

        $team->delete();

        return back()->with('success', 'Team gelöscht');
    }
}
