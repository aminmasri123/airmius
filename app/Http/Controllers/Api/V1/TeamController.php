<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TeamResource;
use App\Models\Club;
use App\Models\Event;
use App\Models\Team;
use App\Models\TeamJoinRequest;
use App\Models\User;
use App\Services\PlanFeatureService;
use App\Support\AppNotification;
use App\Support\ClubRoles;
use App\Support\Roles;
use App\Support\TeamRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TeamController extends Controller
{
    public function __construct(private readonly PlanFeatureService $planFeatures) {}

    public function index(Request $request)
    {
        $teams = Team::visibleTo($request->user())
            ->with(['club.users', 'users', 'joinRequests.user'])
            ->withCount(['users', 'events'])
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return TeamResource::collection($teams);
    }

    public function show(Request $request, Team $team)
    {
        abort_unless(
            Team::visibleTo($request->user())->whereKey($team->id)->exists(),
            404
        );

        $team->loadMissing(['club.users', 'users', 'joinRequests.user'])->loadCount(['users', 'events']);
        $team->setAttribute(
            'attendance_stats',
            $this->canViewTrainingAttendanceStats($request->user(), $team)
                ? $this->trainingAttendanceStats($team)
                : null
        );

        return new TeamResource($team);
    }

    public function requestJoin(Request $request, Team $team)
    {
        $this->authorize('view', $team);

        $team->loadMissing(['club.users', 'users']);

        if (! $team->club?->users->contains('id', $request->user()->id)) {
            throw ValidationException::withMessages([
                'team' => 'Du musst Mitglied im Verein sein, bevor du einem Team beitreten kannst.',
            ]);
        }

        if ($team->users->contains('id', $request->user()->id)) {
            throw ValidationException::withMessages([
                'team' => 'Du bist bereits im Team.',
            ]);
        }

        $existingRequest = TeamJoinRequest::query()
            ->where('team_id', $team->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($existingRequest?->status === 'pending') {
            throw ValidationException::withMessages([
                'team' => 'Deine Beitrittsanfrage wartet bereits auf Freigabe.',
            ]);
        }

        $joinRequest = TeamJoinRequest::updateOrCreate(
            ['team_id' => $team->id, 'user_id' => $request->user()->id],
            ['status' => 'pending', 'responded_at' => null],
        );

        $team->club->users
            ->filter(fn (User $member) => filled(array_intersect($member->pivot?->roles ?: [$member->pivot?->role], ['owner', 'admin', 'manager'])))
            ->each(fn (User $member) => AppNotification::send($member, 'team.join_request', [
                'title' => 'Neue Team-Anfrage',
                'body' => $request->user()->name.' möchte '.$team->name.' beitreten.',
                'url' => route('auth.club-memberships.index'),
                'team_id' => $team->id,
                'club_id' => $team->club_id,
                'join_request_id' => $joinRequest->id,
            ]));

        $freshTeam = $team->fresh()
            ->load(['club.users', 'users', 'joinRequests.user'])
            ->loadCount(['users', 'events']);
        $freshTeam->setAttribute('viewer_pending_join_request_id', $joinRequest->id);
        $freshTeam->setAttribute('can_request_join', false);

        return (new TeamResource($freshTeam))
            ->additional(['message' => 'Beitrittsanfrage gesendet.'])
            ->response()
            ->setStatusCode(201);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Team::class);

        $data = $request->validate([
            'club_id' => ['required', 'exists:clubs,id'],
            'name' => ['required', 'string', 'max:255'],
            'sport_type' => ['nullable', 'string', 'max:120'],
        ]);

        $club = Club::findOrFail($data['club_id']);
        abort_unless($request->user()->can('update', $club), 403);

        if (! Team::query()->where('club_id', $club->id)->where('name', $data['name'])->exists()) {
            $this->planFeatures->ensureCanCreateTeam($club);
        }

        $team = DB::transaction(function () use ($club, $data, $request) {
            $team = Team::firstOrCreate(
                ['club_id' => $club->id, 'name' => $data['name']],
                ['sport_type' => $data['sport_type'] ?? $club->sport_type]
            );

            if (! $team->wasRecentlyCreated) {
                $team->update(['sport_type' => $data['sport_type'] ?? $team->sport_type]);
            }

            $team->users()->syncWithoutDetaching([
                $request->user()->id => ['role' => TeamRoles::COACH],
            ]);

            return $team;
        });

        return (new TeamResource($team->fresh()->load(['club', 'users'])->loadCount(['users', 'events'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Team $team)
    {
        $this->authorize('update', $team);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sport_type' => ['nullable', 'string', 'max:120'],
        ]);

        $team->update([
            'name' => $data['name'],
            'sport_type' => $data['sport_type'] ?? $team->sport_type,
        ]);

        return new TeamResource($team->fresh()->load(['club', 'users'])->loadCount(['users', 'events']));
    }

    public function destroy(Request $request, Team $team)
    {
        $this->authorize('delete', $team);

        $team->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function approveJoinRequest(Request $request, Team $team, TeamJoinRequest $joinRequest)
    {
        abort_unless($joinRequest->team_id === $team->id, 404);
        $this->authorize('invite', $team);

        if ($joinRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'join_request' => 'Diese Team-Beitrittsanfrage wurde bereits bearbeitet.',
            ]);
        }

        $data = $request->validate([
            'role' => ['nullable', Rule::in(Team::ROLES)],
        ]);

        abort_if(
            ! $team->club->users()->where('users.id', $joinRequest->user_id)->exists()
            && ! $team->club->canAddMembers(),
            422,
            'Das Mitgliederlimit des aktuellen Vereinsplans ist erreicht.'
        );

        DB::transaction(function () use ($team, $joinRequest, $data) {
            $team->users()->syncWithoutDetaching([
                $joinRequest->user_id => ['role' => $data['role'] ?? 'Player'],
            ]);

            $team->club->users()->syncWithoutDetaching([
                $joinRequest->user_id => [
                    'role' => 'member',
                    'roles' => ['member'],
                    'membership_status' => 'non_member',
                    'joined_on' => now()->toDateString(),
                ],
            ]);

            $joinRequest->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);
        });

        AppNotification::send($joinRequest->user_id, 'team.join_request_accepted', [
            'title' => 'Team-Beitrittsanfrage akzeptiert',
            'body' => 'Deine Anfrage fuer '.$team->name.' wurde akzeptiert.',
            'team_id' => $team->id,
            'club_id' => $team->club_id,
        ]);

        return new TeamResource($team->fresh()->load(['club.users', 'users', 'joinRequests.user'])->loadCount(['users', 'events']));
    }

    public function approveJoinRequestById(Request $request, TeamJoinRequest $joinRequest)
    {
        return $this->approveJoinRequest($request, $joinRequest->team, $joinRequest);
    }

    public function declineJoinRequest(Request $request, Team $team, TeamJoinRequest $joinRequest)
    {
        abort_unless($joinRequest->team_id === $team->id, 404);
        $this->authorize('update', $team);

        if ($joinRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'join_request' => 'Diese Team-Beitrittsanfrage wurde bereits bearbeitet.',
            ]);
        }

        $joinRequest->update([
            'status' => 'declined',
            'responded_at' => now(),
        ]);

        AppNotification::send($joinRequest->user_id, 'team.join_request_declined', [
            'title' => 'Team-Beitrittsanfrage abgelehnt',
            'body' => 'Deine Anfrage fuer '.$team->name.' wurde abgelehnt.',
            'team_id' => $team->id,
            'club_id' => $team->club_id,
        ]);

        return new TeamResource($team->fresh()->load(['club.users', 'users', 'joinRequests.user'])->loadCount(['users', 'events']));
    }

    public function declineJoinRequestById(Request $request, TeamJoinRequest $joinRequest)
    {
        return $this->declineJoinRequest($request, $joinRequest->team, $joinRequest);
    }

    public function attendanceStats(Request $request, Team $team)
    {
        abort_unless(
            Team::visibleTo($request->user())->whereKey($team->id)->exists(),
            404
        );
        abort_unless($this->canViewTrainingAttendanceStats($request->user(), $team), 403);

        return response()->json([
            'data' => $this->trainingAttendanceStats($team->loadMissing('users')),
        ]);
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->integer('per_page', 20), 1), 50);
    }

    private function trainingAttendanceStats(Team $team): array
    {
        $trainingEvents = Event::query()
            ->where('team_id', $team->id)
            ->where('type', 'training')
            ->where('start_time', '<=', now())
            ->pluck('id');

        $totalTrainings = $trainingEvents->count();
        $statusCounts = DB::table('event_participants')
            ->select('user_id', 'status', DB::raw('COUNT(*) as total'))
            ->whereIn('event_id', $trainingEvents)
            ->groupBy('user_id', 'status')
            ->get()
            ->groupBy('user_id');

        $members = $team->relationLoaded('users')
            ? $team->users
            : $team->users()->select('users.id', 'name', 'email', 'profile_photo_path')->orderBy('name')->get();

        $rows = $members->map(function (User $member) use ($statusCounts, $totalTrainings) {
            $counts = $statusCounts->get($member->id, collect())
                ->pluck('total', 'status');
            $yes = (int) ($counts['yes'] ?? 0);
            $late = (int) ($counts['late'] ?? 0);
            $maybe = (int) ($counts['maybe'] ?? 0);
            $no = (int) ($counts['no'] ?? 0);
            $responded = $yes + $late + $maybe + $no;
            $attended = $yes + $late;
            $noResponse = max(0, $totalTrainings - $responded);

            return [
                'user_id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'profile_photo_url' => $member->profile_photo_url,
                'trainings_total' => $totalTrainings,
                'attended' => $attended,
                'yes' => $yes,
                'late' => $late,
                'maybe' => $maybe,
                'no' => $no,
                'no_response' => $noResponse,
                'attendance_rate' => $totalTrainings > 0 ? round(($attended / $totalTrainings) * 100, 1) : 0,
            ];
        })->sortByDesc('attendance_rate')->values();

        return [
            'event_type' => 'training',
            'trainings_total' => $totalTrainings,
            'members_total' => $rows->count(),
            'members' => $rows,
        ];
    }

    private function canViewTrainingAttendanceStats(User $user, Team $team): bool
    {
        if ($user->hasAnyRole(Roles::FULL_ACCESS)) {
            return true;
        }

        if ($team->club_id) {
            $team->loadMissing('club');

            if ($team->club) {
                $clubQuery = $team->club->users()->where('users.id', $user->id);

                if (tap($clubQuery, fn ($query) => ClubRoles::whereAny($query, ['owner', 'admin', 'manager']))->exists()) {
                    return true;
                }
            }
        }

        return $team->users()
            ->where('users.id', $user->id)
            ->wherePivot('role', TeamRoles::COACH)
            ->exists();
    }
}
