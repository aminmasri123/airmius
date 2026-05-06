<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubContributionRule;
use App\Models\ClubMembershipType;
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
                ->with([
                    'membershipTypes' => fn ($query) => $query
                        ->where('is_active', true)
                        ->where('is_public', true)
                        ->orderBy('sort_order')
                        ->orderBy('name'),
                    'contributionRules' => fn ($query) => $query
                        ->effectiveOn(now()->toDateString())
                        ->where('is_active', true)
                        ->orderByDesc('valid_from'),
                ])
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
                ->get(['id', 'name', 'sport_type', 'logo', 'country', 'postal_code', 'city', 'state', 'is_official', 'official_club_number', 'membership_requests_enabled'])
                ->map(function (Club $club) {
                    return [
                        'id' => $club->id,
                        'name' => $club->name,
                        'sport_type' => $club->sport_type,
                        'logo' => $club->logo,
                        'country' => $club->country,
                        'postal_code' => $club->postal_code,
                        'city' => $club->city,
                        'state' => $club->state,
                        'is_official' => $club->is_official,
                        'official_club_number' => $club->official_club_number,
                        'membership_requests_enabled' => $club->membership_requests_enabled,
                        'teams_count' => $club->teams_count,
                        'membership_types' => $club->membershipTypes->map(function (ClubMembershipType $type) use ($club) {
                            $rule = $club->contributionRules
                                ->first(fn (ClubContributionRule $rule) => $rule->club_membership_type_id === $type->id)
                                ?: $club->contributionRules->first(fn (ClubContributionRule $rule) => $rule->club_membership_type_id === null);

                            return [
                                'id' => $type->id,
                                'name' => $type->name,
                                'description' => $type->description,
                                'amount' => $rule?->amount,
                                'billing_interval' => $rule?->billing_interval,
                            ];
                        })->values(),
                    ];
                }),
        ]);
    }
}
