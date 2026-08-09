<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubContributionRule;
use App\Models\ClubMembershipType;
use App\Models\Sport;
use App\Services\PublicDiscoveryService;
use App\Support\UploadStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class PublicClubController extends Controller
{
    /**
     * Return a privacy-safe club catalogue for the mobile guest portal.
     *
     * Membership, finance and private member data never leave the protected
     * authenticated club endpoints.
     */
    public function indexJson(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'sport' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:120'],
        ]);

        $clubs = Club::query()
            ->verified()
            ->where('is_listed', true)
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
            ->when($filters['q'] ?? null, fn ($query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->when($filters['sport'] ?? null, fn ($query, string $sport) => $query->where(function ($nested) use ($sport) {
                $nested->where('sport_type', 'like', "%{$sport}%")
                    ->orWhereHas('teams', fn ($teamQuery) => $teamQuery->where('sport_type', 'like', "%{$sport}%"));
            }))
            ->when($filters['location'] ?? null, fn ($query, string $location) => $query->where(function ($nested) use ($location) {
                $nested->where('city', 'like', "%{$location}%")
                    ->orWhere('postal_code', 'like', "%{$location}%")
                    ->orWhere('country', 'like', "%{$location}%");
            }))
            ->orderBy('name')
            ->limit(60)
            ->get([
                'id',
                'name',
                'sport_type',
                'logo',
                'country',
                'postal_code',
                'city',
                'state',
                'is_official',
                'official_club_number',
                'membership_requests_enabled',
            ]);

        return response()->json([
            'data' => $clubs->map(function (Club $club) {
                return [
                    'id' => $club->id,
                    'name' => $club->name,
                    'sport_type' => $club->sport_type,
                    'logo_url' => UploadStorage::url($club->logo),
                    'country' => $club->country,
                    'postal_code' => $club->postal_code,
                    'city' => $club->city,
                    'state' => $club->state,
                    'is_official' => (bool) $club->is_official,
                    'official_club_number' => $club->official_club_number,
                    'membership_requests_enabled' => (bool) $club->membership_requests_enabled,
                    'teams_count' => (int) ($club->teams_count ?? 0),
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
            })->values(),
            'meta' => [
                'total' => $clubs->count(),
                'limit' => 60,
            ],
        ]);
    }

    public function index(Request $request, PublicDiscoveryService $discovery)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'sport_type' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:120'],
        ]);

        return Inertia::render('Guest/Vereine', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'filters' => $filters,
            'sports' => fn () => Sport::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'category']),
            'clubs' => fn () => $discovery->clubs($filters),
        ]);
    }
}
