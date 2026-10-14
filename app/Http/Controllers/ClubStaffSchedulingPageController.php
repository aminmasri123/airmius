<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Support\ClubPermissions;
use App\Support\Roles;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClubStaffSchedulingPageController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $clubs = Club::query()
            ->when(! $user->hasAnyRole(Roles::FULL_ACCESS), fn ($query) => $query->linkedToUser($user))
            ->orderBy('name')
            ->get(['id', 'name', 'owner_id'])
            ->filter(fn (Club $club) => $club->owner_id === $user->id
                || ClubPermissions::allows($club, $user, ClubPermissions::EVENTS_MANAGE)
                || ClubPermissions::allows($club, $user, ClubPermissions::TRAINING_SESSIONS_EDIT)
                || ClubPermissions::allows($club, $user, ClubPermissions::MEMBERS_EDIT)
                || $club->users()
                    ->where('users.id', $user->id)
                    ->wherePivot('membership_status', 'active')
                    ->exists())
            ->map(fn (Club $club) => ['id' => $club->id, 'name' => $club->name])
            ->values();

        abort_if($clubs->isEmpty(), 403);

        return Inertia::render('Auth/Dashboard/ClubStaffScheduling/Index', [
            'clubs' => $clubs,
        ]);
    }
}
