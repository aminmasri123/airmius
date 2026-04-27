<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamJoinRequest;
use App\Models\User;
use App\Support\AppNotification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TeamController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', Team::class);

        $clubs = Club::query()
            ->visibleTo(auth()->user())
            ->orderBy('name')
            ->with([
                'admins:id,name,email',
                'sponsors',
                'teams' => fn ($query) => $query
                    ->withCount('users')
                    ->with([
                        'users:id,name,email',
                        'invitations' => fn ($query) => $query
                            ->where('status', 'pending')
                            ->with('recipient:id,name,email'),
                        'joinRequests' => fn ($query) => $query
                            ->where('status', 'pending')
                            ->with('user:id,name,email'),
                    ]),
            ])
            ->get();
        return Inertia::render('Auth/Dashboard/Teams/Index', [
            'clubs' => $clubs,
            'availableUsers' => User::query()
                ->select(['id', 'name', 'email'])
                ->orderBy('name')
                ->limit(200)
                ->get(),
            'teamRoles' => Team::ROLES,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Team::class);

        $data = $request->validate([
            'club_id' => ['required', 'exists:clubs,id'],
            'name' => ['required', 'string', 'max:255'],
            'sport_type' => ['nullable', 'string', 'max:80'],
        ]);

        $club = Club::findOrFail($data['club_id']);

        abort_unless(
            $club->users()->where('users.id', auth()->id())->exists() || auth()->user()->can('org.manage'),
            403
        );

        Team::create([
            'name' => $data['name'],
            'club_id' => $club->id,
            'sport_type' => $data['sport_type'] ?? 'football',
        ]);

        return back()->with('success', 'Team erstellt');
    }

    public function update(Request $request, Team $team)
    {
        $this->authorize('update', $team);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sport_type' => ['nullable', 'string', 'max:80'],
        ]);

        $team->update([
            'name' => $data['name'],
            'sport_type' => $data['sport_type'] ?? $team->sport_type,
        ]);

        return back()->with('success', 'Team aktualisiert');
    }

    public function show(Team $team)
    {
        $this->authorize('view', $team);

        return redirect()->route('auth.teams.index');
    }

    public function destroy(Team $team)
    {
        $this->authorize('delete', $team);

        $team->delete();

        return back()->with('success', 'Team geloescht');
    }

    public function invite(Request $request, Team $team)
    {
        $this->authorize('update', $team);
        abort_unless($request->user()->can('team.invite'), 403);

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['required', Rule::in(Team::ROLES)],
        ]);

        abort_if($team->users()->where('users.id', $data['user_id'])->exists(), 422, 'User ist bereits im Team.');

        $invitation = TeamInvitation::updateOrCreate(
            ['team_id' => $team->id, 'recipient_id' => $data['user_id']],
            [
                'inviter_id' => $request->user()->id,
                'role' => $data['role'],
                'status' => 'pending',
                'responded_at' => null,
            ],
        );

        AppNotification::send($data['user_id'], 'team.invite', [
            'title' => 'Einladung zu '.$team->name,
            'body' => 'Du wurdest als '.$data['role'].' eingeladen.',
            'url' => route('auth.teams.index'),
            'team_id' => $team->id,
            'invitation_id' => $invitation->id,
        ]);

        return back()->with('success', 'Einladung gesendet.');
    }

    public function acceptInvitation(Request $request, TeamInvitation $invitation)
    {
        abort_unless($invitation->recipient_id === $request->user()->id, 403);
        abort_unless($invitation->status === 'pending', 422);

        DB::transaction(function () use ($invitation) {
            $invitation->team->users()->syncWithoutDetaching([
                $invitation->recipient_id => ['role' => $invitation->role],
            ]);

            $invitation->team->club->users()->syncWithoutDetaching([
                $invitation->recipient_id => ['role' => 'member'],
            ]);

            $invitation->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);
        });

        return back()->with('success', 'Einladung angenommen.');
    }

    public function requestJoin(Request $request, Team $team)
    {
        abort_if($team->users()->where('users.id', $request->user()->id)->exists(), 422, 'Du bist bereits im Team.');

        TeamJoinRequest::updateOrCreate(
            ['team_id' => $team->id, 'user_id' => $request->user()->id],
            ['status' => 'pending', 'responded_at' => null],
        );

        return back()->with('success', 'Beitrittsanfrage gesendet.');
    }

    public function approveJoinRequest(Request $request, TeamJoinRequest $joinRequest)
    {
        $this->authorize('update', $joinRequest->team);
        abort_unless($request->user()->can('team.invite'), 403);
        abort_unless($joinRequest->status === 'pending', 422);

        $data = $request->validate([
            'role' => ['nullable', Rule::in(Team::ROLES)],
        ]);

        DB::transaction(function () use ($joinRequest, $data) {
            $joinRequest->team->users()->syncWithoutDetaching([
                $joinRequest->user_id => ['role' => $data['role'] ?? 'Player'],
            ]);

            $joinRequest->team->club->users()->syncWithoutDetaching([
                $joinRequest->user_id => ['role' => 'member'],
            ]);

            $joinRequest->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);
        });

        return back()->with('success', 'Beitrittsanfrage angenommen.');
    }

    public function declineJoinRequest(Request $request, TeamJoinRequest $joinRequest)
    {
        $this->authorize('update', $joinRequest->team);
        abort_unless($joinRequest->status === 'pending', 422);

        $joinRequest->update([
            'status' => 'declined',
            'responded_at' => now(),
        ]);

        return back()->with('success', 'Beitrittsanfrage abgelehnt.');
    }

    public function updateMember(Request $request, Team $team, User $user)
    {
        $this->authorize('update', $team);

        $data = $request->validate([
            'role' => ['required', Rule::in(Team::ROLES)],
        ]);

        $team->users()->updateExistingPivot($user->id, [
            'role' => $data['role'],
        ]);

        return back()->with('success', 'Teamrolle aktualisiert.');
    }

    public function removeMember(Request $request, Team $team, User $user)
    {
        $this->authorize('update', $team);
        abort_unless($request->user()->can('team.kick'), 403);

        $team->users()->detach($user->id);

        return back()->with('success', 'Mitglied entfernt.');
    }
}
