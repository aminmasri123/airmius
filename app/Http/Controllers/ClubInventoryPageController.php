<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\User;
use App\Support\ClubPermissions;
use App\Support\ClubRoles;
use App\Support\Roles;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClubInventoryPageController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $clubs = Club::query()
            ->when(! $user->hasAnyRole(Roles::FULL_ACCESS), function ($query) use ($user) {
                $query->where(function ($clubQuery) use ($user) {
                    $clubQuery->where('owner_id', $user->id)
                        ->orWhereHas('users', function ($memberQuery) use ($user) {
                            $memberQuery->where('users.id', $user->id);
                            ClubRoles::whereAny($memberQuery, ClubRoles::ELEVATED);
                        });
                });
            })
            ->with(['users' => fn ($query) => $query
                ->where(function ($memberQuery) {
                    $memberQuery->whereNull('club_user.membership_status')
                        ->orWhere('club_user.membership_status', 'active');
                })
                ->select('users.id', 'users.name')])
            ->orderBy('name')
            ->get()
            ->filter(fn (Club $club) => ClubPermissions::allows($club, $user, ClubPermissions::INVENTORY_MANAGE))
            ->map(fn (Club $club) => [
                'id' => $club->id,
                'name' => $club->name,
                'members' => $club->users->map(fn (User $member) => ['id' => $member->id, 'name' => $member->name])->values(),
            ])
            ->values();

        abort_if($clubs->isEmpty(), 403);

        return Inertia::render('Auth/Dashboard/ClubInventory/Index', ['clubs' => $clubs]);
    }
}
