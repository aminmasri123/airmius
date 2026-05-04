<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Post;
use App\Models\Sport;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamJoinRequest;
use App\Models\User;
use App\Notifications\ExternalTeamInvitation;
use App\Services\MediaOptimizer;
use App\Services\PlanFeatureService;
use App\Support\UploadStorage;
use App\Support\Roles;
use App\Support\AppNotification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TeamController extends Controller
{
    use AuthorizesRequests;

    public const CLUB_MEMBER_ROLES = ['owner', 'admin', 'manager', 'member'];

    public function __construct(
        private MediaOptimizer $mediaOptimizer,
        private PlanFeatureService $planFeatures,
    ) {}

    public function index()
    {
        $this->authorize('viewAny', Team::class);

        $user = auth()->user();
        $filters = request()->only(['search', 'sport_type', 'location']);
        $clubs = Club::query()
            ->where(function ($query) use ($user) {
                $query->whereHas('users', fn ($userQuery) => $userQuery->where('users.id', $user->id))
                    ->orWhereHas('teams.users', fn ($userQuery) => $userQuery->where('users.id', $user->id));
            })
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->when($filters['sport_type'] ?? null, fn ($query, $sport) => $query->where(function ($query) use ($sport, $user) {
                $query->where('sport_type', 'like', "%{$sport}%")
                    ->orWhereHas('teams', fn ($teamQuery) => $teamQuery
                        ->whereHas('users', fn ($userQuery) => $userQuery->where('users.id', $user->id))
                        ->where('sport_type', 'like', "%{$sport}%"));
            }))
            ->when($filters['location'] ?? null, fn ($query, $location) => $query->where(function ($query) use ($location) {
                $query->where('city', 'like', "%{$location}%")
                    ->orWhere('postal_code', 'like', "%{$location}%")
                    ->orWhere('country', 'like', "%{$location}%");
            }))
            ->orderBy('name')
            ->with([
                'admins:id,name,email',
                'users:id,name,email,profile_photo_path',
                'sponsors',
                'jobs' => fn ($query) => $query->latest('id'),
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
            ->get()
            ->each(function (Club $club) use ($user) {
                $club->setAttribute('can_manage', $user->can('update', $club));
                $club->setAttribute('can_delete', $user->can('delete', $club));
                $club->setAttribute('subscription_capabilities', $this->planFeatures->capabilities($club));
                $teams = $club->can_manage
                    ? $club->teams
                    : $club->teams->filter(fn (Team $team) => $team->users->contains('id', $user->id))->values();

                $teams->each(function (Team $team) use ($user) {
                    $team->setAttribute('can_manage', $user->can('update', $team));
                    $team->setAttribute('can_delete', $user->can('delete', $team));
                });
                $club->setRelation('teams', $teams);
            });
        return Inertia::render('Auth/Dashboard/Teams/Index', [
            'clubs' => $clubs,
            'availableUsers' => User::query()
                ->select(['id', 'name', 'email'])
                ->orderBy('name')
                ->limit(200)
                ->get(),
            'teamRoles' => Team::ROLES,
            'clubRoles' => self::CLUB_MEMBER_ROLES,
            'filters' => $filters,
            'sports' => Sport::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'category']),
        ]);
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

        $user = $request->user();

        abort_unless(
            $club->users()->where('users.id', $user->id)->exists()
                || $user->hasAnyRole(Roles::FULL_ACCESS)
                || $user->can('org.manage'),
            403
        );

        if (! Team::query()->where('club_id', $club->id)->where('name', $data['name'])->exists()) {
            $this->planFeatures->ensureCanCreateTeam($club);
        }

        DB::transaction(function () use ($club, $data, $user) {
            $team = Team::firstOrCreate(
                [
                    'club_id' => $club->id,
                    'name' => $data['name'],
                ],
                [
                    'sport_type' => $data['sport_type'] ?? 'fussball',
                ],
            );

            if (! $team->wasRecentlyCreated) {
                $team->update([
                    'sport_type' => $data['sport_type'] ?? $team->sport_type,
                ]);
            }

            $team->users()->syncWithoutDetaching([
                $user->id => ['role' => 'Coach'],
            ]);
        });

        return back()->with('success', 'Team erstellt');
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

        return back()->with('success', 'Team aktualisiert');
    }

    public function show(Request $request, Team $team)
    {
        $this->authorize('view', $team);

        $viewer = $request->user();
        $isMember = $team->users()->where('users.id', $viewer->id)->exists();
        $hasPendingJoinRequest = $team->joinRequests()
            ->where('user_id', $viewer->id)
            ->where('status', 'pending')
            ->exists();
        $canManage = $viewer->can('update', $team);

        $team->loadCount(['users', 'events', 'files']);
        $team->load([
            'club:id,name,logo,cover_image',
            'users' => fn ($query) => $query
                ->select('users.id', 'name', 'email', 'profile_photo_path')
                ->orderBy('name')
                ->limit(18),
        ]);

        $posts = Post::query()
            ->where('team_id', $team->id)
            ->where('moderation_status', '!=', 'removed')
            ->where(function ($query) use ($viewer, $isMember) {
                $query->where('visibility', 'public')
                    ->orWhere('user_id', $viewer->id)
                    ->when($isMember, fn ($query) => $query->orWhere('visibility', 'team'));
            })
            ->with(['user:id,name,profile_photo_path', 'club:id,name'])
            ->withCount([
                'comments' => fn ($query) => $query->where('moderation_status', '!=', 'removed'),
                'likes',
            ])
            ->latest('id')
            ->limit(8)
            ->get();

        return Inertia::render('Auth/Dashboard/Teams/Profile', [
            'teamProfile' => [
                'id' => $team->id,
                'name' => $team->name,
                'sport_type' => $team->sport_type,
                'logo' => $team->logo,
                'cover_image' => $team->cover_image,
                'club' => $team->club,
                'users_count' => $team->users_count,
                'events_count' => $team->events_count,
                'files_count' => $team->files_count,
                'members' => $team->users,
            ],
            'posts' => $posts,
            'viewer' => [
                'is_member' => $isMember,
                'has_pending_join_request' => $hasPendingJoinRequest,
                'can_manage' => $canManage,
            ],
        ]);
    }

    public function updateImages(Request $request, Team $team)
    {
        $this->authorize('update', $team);

        $data = $request->validate([
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        abort_unless($request->hasFile('logo') || $request->hasFile('cover_image'), 422);

        $updates = [];

        if ($request->hasFile('logo')) {
            if ($team->logo) {
                Storage::disk(UploadStorage::disk())->delete($team->logo);
            }

            $updates['logo'] = $this->mediaOptimizer->store($request->file('logo'), 'teams/'.$team->id.'/profile')['path'];
        }

        if ($request->hasFile('cover_image')) {
            if ($team->cover_image) {
                Storage::disk(UploadStorage::disk())->delete($team->cover_image);
            }

            $updates['cover_image'] = $this->mediaOptimizer->store($request->file('cover_image'), 'teams/'.$team->id.'/profile')['path'];
        }

        $team->update($updates);

        return back()->with('success', 'Teambilder aktualisiert.');
    }

    public function destroy(Team $team)
    {
        $this->authorize('delete', $team);

        $team->delete();

        return back()->with('success', 'Team gelöscht');
    }

    public function invite(Request $request, Team $team)
    {
        $this->authorize('invite', $team);

        $data = $request->validate([
            'email' => ['required_without:user_id', 'nullable', 'email', 'max:255'],
            'user_id' => ['nullable', 'exists:users,id'],
            'role' => ['required', Rule::in(Team::ROLES)],
        ]);

        $recipient = ! empty($data['user_id'])
            ? User::findOrFail($data['user_id'])
            : User::where('email', strtolower($data['email']))->first();

        if ($recipient) {
            if ($team->users()->where('users.id', $recipient->id)->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'User ist bereits im Team.',
                ]);
            }

            $invitation = TeamInvitation::updateOrCreate(
                ['team_id' => $team->id, 'recipient_id' => $recipient->id],
                [
                    'inviter_id' => $request->user()->id,
                    'email' => $recipient->email,
                    'token' => Str::random(64),
                    'role' => $data['role'],
                    'status' => 'pending',
                    'responded_at' => null,
                ],
            );

            AppNotification::send($recipient->id, 'team.invite', [
                'title' => 'Einladung zu '.$team->name,
                'body' => 'Du wurdest als '.$data['role'].' eingeladen.',
                'url' => route('auth.teams.index'),
                'team_id' => $team->id,
                'invitation_id' => $invitation->id,
            ]);

            return back()->with('success', 'Einladung gesendet.');
        }

        $email = strtolower($data['email']);
        $this->planFeatures->ensureAllows($team->club, 'member_invitations');

        $invitation = TeamInvitation::updateOrCreate(
            ['team_id' => $team->id, 'email' => $email],
            [
                'inviter_id' => $request->user()->id,
                'recipient_id' => null,
                'token' => Str::random(64),
                'role' => $data['role'],
                'status' => 'pending',
                'responded_at' => null,
            ],
        );

        Notification::route('mail', $email)->notify(new ExternalTeamInvitation($invitation->load('team')));

        return back()->with('success', 'Einladung per E-Mail gesendet.');
    }

    public function acceptInvitation(Request $request, TeamInvitation $invitation)
    {
        abort_unless($invitation->recipient_id === $request->user()->id, 403);
        abort_unless($invitation->status === 'pending', 422);
        abort_if(
            ! $invitation->team->club->users()->where('users.id', $invitation->recipient_id)->exists()
            && ! $invitation->team->club->canAddMembers(),
            422,
            'Das Mitgliederlimit des aktuellen Vereinsplans ist erreicht.'
        );

        DB::transaction(function () use ($invitation) {
            $invitation->team->users()->syncWithoutDetaching([
                $invitation->recipient_id => ['role' => $invitation->role],
            ]);

            $invitation->team->club->users()->syncWithoutDetaching([
                $invitation->recipient_id => [
                    'role' => 'member',
                    'membership_status' => 'non_member',
                    'joined_on' => now()->toDateString(),
                ],
            ]);

            $invitation->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);
        });

        return back()->with('success', 'Einladung angenommen.');
    }

    public function acceptInvitationByToken(Request $request, string $token)
    {
        $invitation = TeamInvitation::query()
            ->where('token', $token)
            ->where('status', 'pending')
            ->firstOrFail();

        abort_unless(strtolower((string) $invitation->email) === strtolower($request->user()->email), 403);
        abort_if(
            ! $invitation->team->club->users()->where('users.id', $request->user()->id)->exists()
            && ! $invitation->team->club->canAddMembers(),
            422,
            'Das Mitgliederlimit des aktuellen Vereinsplans ist erreicht.'
        );

        DB::transaction(function () use ($invitation, $request) {
            $invitation->team->users()->syncWithoutDetaching([
                $request->user()->id => ['role' => $invitation->role],
            ]);

            $invitation->team->club->users()->syncWithoutDetaching([
                $request->user()->id => [
                    'role' => 'member',
                    'membership_status' => 'non_member',
                    'joined_on' => now()->toDateString(),
                ],
            ]);

            $invitation->update([
                'recipient_id' => $request->user()->id,
                'status' => 'accepted',
                'responded_at' => now(),
            ]);
        });

        return redirect()->route('auth.teams.index')->with('success', 'Einladung angenommen.');
    }

    public function requestJoin(Request $request, Team $team)
    {
        $this->authorize('view', $team);

        abort_if($team->users()->where('users.id', $request->user()->id)->exists(), 422, 'Du bist bereits im Team.');

        $joinRequest = TeamJoinRequest::updateOrCreate(
            ['team_id' => $team->id, 'user_id' => $request->user()->id],
            ['status' => 'pending', 'responded_at' => null],
        );

        $team->load('club.users');

        $team->club->users
            ->filter(fn (User $member) => in_array($member->pivot?->role, ['owner', 'admin', 'manager'], true))
            ->each(fn (User $member) => AppNotification::send($member, 'team.join_request', [
                'title' => 'Neue Team-Anfrage',
                'body' => $request->user()->name.' möchte '.$team->name.' beitreten.',
                'url' => route('auth.club-memberships.index'),
                'team_id' => $team->id,
                'join_request_id' => $joinRequest->id,
            ]));

        return back()->with('success', 'Beitrittsanfrage gesendet.');
    }

    public function approveJoinRequest(Request $request, TeamJoinRequest $joinRequest)
    {
        $this->authorize('invite', $joinRequest->team);
        abort_unless($joinRequest->status === 'pending', 422);

        $data = $request->validate([
            'role' => ['nullable', Rule::in(Team::ROLES)],
        ]);
        abort_if(
            ! $joinRequest->team->club->users()->where('users.id', $joinRequest->user_id)->exists()
            && ! $joinRequest->team->club->canAddMembers(),
            422,
            'Das Mitgliederlimit des aktuellen Vereinsplans ist erreicht.'
        );

        DB::transaction(function () use ($joinRequest, $data) {
            $joinRequest->team->users()->syncWithoutDetaching([
                $joinRequest->user_id => ['role' => $data['role'] ?? 'Player'],
            ]);

            $joinRequest->team->club->users()->syncWithoutDetaching([
                $joinRequest->user_id => [
                    'role' => 'member',
                    'membership_status' => 'non_member',
                    'joined_on' => now()->toDateString(),
                ],
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
