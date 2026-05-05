<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Sport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class PublicClubController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'sport_type', 'location']);

        return Inertia::render('Guest/Vereine', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'filters' => $filters,
            'sports' => Sport::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'category']),
            'clubs' => Club::query()
                ->verified()
                ->withCount('teams')
                ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
                ->when($filters['sport_type'] ?? null, fn ($query, $sport) => $query->where(function ($query) use ($sport) {
                    $query->where('sport_type', 'like', "%{$sport}%")
                        ->orWhereHas('teams', fn ($teamQuery) => $teamQuery->where('sport_type', 'like', "%{$sport}%"));
                }))
                ->when($filters['location'] ?? null, fn ($query, $location) => $query->where(function ($query) use ($location) {
                    $query->where('city', 'like', "%{$location}%")
                        ->orWhere('postal_code', 'like', "%{$location}%")
                        ->orWhere('country', 'like', "%{$location}%");
                }))
                ->orderBy('name')
                ->limit(60)
                ->get(['id', 'name', 'sport_type', 'logo', 'country', 'postal_code', 'city', 'state', 'is_official', 'official_club_number']),
        ]);
    }
}
