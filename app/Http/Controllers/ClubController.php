<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Services\ClubService;
use Illuminate\Http\Request;
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
        /*    $this->service->create(auth()->user(), $request->validate([
               'name' => 'required|string|max:255'
           ]));

           return back();
           */

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $club = Club::create([
            'name' => $request->name,
        ]);

        // 🔥 User direkt zuweisen
        $club->users()->attach(auth()->id());

        return back()->with('success', 'Club erstellt');

    }

    public function destroy(Club $club)
    {
        $this->service->delete($club);

        return back();
    }
}
