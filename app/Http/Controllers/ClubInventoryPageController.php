<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\User;
use App\Support\ClubPermissions;
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
                        });
                });
            })
            ->with([
                'users' => fn ($query) => $query
                    ->where(function ($memberQuery) {
                        $memberQuery->whereNull('club_user.membership_status')
                            ->orWhere('club_user.membership_status', 'active');
                    })
                    ->select('users.id', 'users.name'),
                'departments:id,club_id,name',
                'teams:id,club_id,club_department_id,name',
            ])
            ->orderBy('name')
            ->get()
            ->filter(fn (Club $club) => collect([
                ClubPermissions::INVENTORY_VIEW,
                ClubPermissions::INVENTORY_EDIT,
                ClubPermissions::INVENTORY_APPROVE,
                ClubPermissions::INVENTORY_DELETE,
            ])->contains(fn (string $permission) => ClubPermissions::allowsAnyInventoryScope($club, $user, $permission)))
            ->map(function (Club $club) use ($user) {
                $canEditGlobally = ClubPermissions::allows($club, $user, ClubPermissions::INVENTORY_EDIT);
                $editableTeams = $club->teams
                    ->filter(fn ($team) => $canEditGlobally || ClubPermissions::allowsForTeam(
                        $team, $user, ClubPermissions::INVENTORY_EDIT,
                    ));
                $editableTeamDepartmentIds = $editableTeams->pluck('club_department_id')->filter()->map(fn ($id) => (int) $id);

                return [
                    'id' => $club->id,
                    'name' => $club->name,
                    'members' => $club->users->map(fn (User $member) => ['id' => $member->id, 'name' => $member->name])->values(),
                    'departments' => $club->departments
                        ->filter(fn ($department) => $canEditGlobally || ClubPermissions::allowsInScope(
                            $club, $user, ClubPermissions::INVENTORY_EDIT, 'department', (int) $department->id,
                        ) || $editableTeamDepartmentIds->contains((int) $department->id))
                        ->map(fn ($department) => ['id' => $department->id, 'name' => $department->name])
                        ->values(),
                    'teams' => $editableTeams
                        ->map(fn ($team) => [
                            'id' => $team->id,
                            'name' => $team->name,
                            'club_department_id' => $team->club_department_id,
                        ])
                        ->values(),
                    'can_edit_inventory_globally' => $canEditGlobally,
                ];
            })
            ->values();

        abort_if($clubs->isEmpty(), 403);

        return Inertia::render('Auth/Dashboard/ClubInventory/Index', ['clubs' => $clubs]);
    }
}
