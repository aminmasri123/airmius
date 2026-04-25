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
        return Inertia::render('Clubs/Index', [
            'clubs' => auth()->user()->clubs
        ]);
    }

    public function store(Request $request)
    {
        $this->service->create(auth()->user(), $request->validate([
            'name' => 'required|string|max:255'
        ]));

        return back();
    }

    public function destroy(Club $club)
    {
        $this->service->delete($club);
        return back();
    }
}
