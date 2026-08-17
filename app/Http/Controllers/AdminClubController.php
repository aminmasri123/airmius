<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Services\ClubService;
use App\Support\AppNotification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AdminClubController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly ClubService $clubService) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'query' => ['nullable', 'string', 'max:120'],
            'verification' => ['nullable', Rule::in(['pending_verification', 'verified', 'rejected'])],
        ]);
        $search = trim((string) ($filters['query'] ?? ''));
        $verification = (string) ($filters['verification'] ?? '');

        $clubs = Club::query()
            ->with([
                'owner:id,name,email',
                'currentSubscription.plan:id,name',
            ])
            ->withCount(['users', 'externalMembers', 'teams'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('city', 'like', '%'.$search.'%')
                        ->orWhere('official_club_number', 'like', '%'.$search.'%')
                        ->orWhereHas('owner', fn ($ownerQuery) => $ownerQuery
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%'));
                });
            })
            ->when($verification !== '', fn ($query) => $query->where('verification_status', $verification))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Club $club) => [
                'id' => $club->id,
                'name' => $club->name,
                'sport_type' => $club->sport_type,
                'city' => $club->city,
                'country' => $club->country,
                'official_club_number' => $club->official_club_number,
                'verification_status' => $club->verification_status,
                'is_listed' => (bool) $club->is_listed,
                'owner' => $club->owner,
                'users_count' => $club->users_count,
                'external_members_count' => $club->external_members_count,
                'members_count' => $club->users_count + $club->external_members_count,
                'teams_count' => $club->teams_count,
                'plan' => $club->currentSubscription?->plan,
                'created_at' => $club->created_at?->toDateString(),
            ]);

        return Inertia::render('Auth/Dashboard/Admin/Clubs/Index', [
            'clubs' => $clubs,
            'filters' => [
                'query' => $search,
                'verification' => $verification,
            ],
            'summary' => [
                'total' => Club::query()->count(),
                'verified' => Club::query()->where('verification_status', 'verified')->count(),
                'pending' => Club::query()->where('verification_status', 'pending_verification')->count(),
                'unlisted' => Club::query()->where('is_listed', false)->count(),
            ],
            'canDeleteClubs' => $request->user()?->hasRole('super_admin') ?? false,
        ]);
    }

    public function destroy(Request $request, Club $club)
    {
        abort_unless(
            $request->user()?->hasRole('super_admin'),
            403,
            __('organization.admin_clubs.super_admin_required'),
        );
        $this->authorize('delete', $club);

        $request->merge([
            'confirmation_name' => trim((string) $request->input('confirmation_name')),
        ]);
        $request->validate([
            'confirmation_name' => ['required', 'string', Rule::in([$club->name])],
        ], [
            'confirmation_name.in' => __('organization.admin_clubs.name_mismatch'),
        ]);

        $clubName = $club->name;
        $owner = $club->owner;

        $this->clubService->delete($club);

        if ($owner) {
            AppNotification::sendLocalized(
                $owner,
                'club.deleted_by_platform',
                'organization.notifications.club_deleted_by_platform_title',
                'organization.notifications.club_deleted_by_platform_body',
                ['club' => $clubName],
                ['url' => route('auth.dashboard')],
            );
        }

        return back()->with('success', __('organization.admin_clubs.deleted', ['club' => $clubName]));
    }
}
