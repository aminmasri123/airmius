<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Conversation;
use App\Models\Invoice;
use App\Models\Post;
use App\Models\Sport;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamJoinRequest;
use App\Models\User;
use App\Models\UserBadge;
use App\Notifications\ExternalTeamInvitation;
use App\Services\MediaOptimizer;
use App\Services\GamificationService;
use App\Services\PlanFeatureService;
use App\Support\UploadStorage;
use App\Support\ClubRoles;
use App\Support\Roles;
use App\Support\AppNotification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Throwable;

class TeamController extends Controller
{
    use AuthorizesRequests;

    public const CLUB_MEMBER_ROLES = ['owner', 'admin', 'manager', 'member'];

    public function __construct(
        private MediaOptimizer $mediaOptimizer,
        private PlanFeatureService $planFeatures,
        private GamificationService $gamification,
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
                'sponsors' => fn ($query) => $query->latest('id'),
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
                $club->setAttribute('can_manage_jobs', $this->canManageJobsForClub($user, $club));
                $club->setAttribute('can_delete', $user->can('delete', $club));
                $club->setAttribute('subscription_capabilities', $this->planFeatures->capabilities($club));
                $club->setRelation('sponsors', $club->sponsors->map(fn ($sponsor) => [
                    'id' => $sponsor->id,
                    'name' => $sponsor->name,
                    'contact_name' => $sponsor->contact_name,
                    'email' => $sponsor->email,
                    'website' => $sponsor->website,
                    'logo' => $sponsor->logo,
                    'logo_light' => $sponsor->logo_light,
                    'logo_dark' => $sponsor->logo_dark,
                    'logo_url' => UploadStorage::url($sponsor->logo),
                    'logo_light_url' => UploadStorage::url($sponsor->logo_light ?: $sponsor->logo),
                    'logo_dark_url' => UploadStorage::url($sponsor->logo_dark ?: $sponsor->logo_light ?: $sponsor->logo),
                    'amount' => $sponsor->amount,
                    'starts_at' => $sponsor->starts_at?->toDateString(),
                    'ends_at' => $sponsor->ends_at?->toDateString(),
                ]));
                $isClubMember = $club->users->contains('id', $user->id);
                $canSeeClubTeams = $isClubMember;
                $teams = $club->can_manage || $canSeeClubTeams
                    ? $club->teams
                    : $club->teams->filter(fn (Team $team) => $team->users->contains('id', $user->id))->values();

                $teams->each(function (Team $team) use ($user, $canSeeClubTeams) {
                    $viewerIsMember = $team->users->contains('id', $user->id);
                    $pendingJoinRequest = $team->joinRequests->firstWhere('user_id', $user->id);
                    $canHandleJoinRequests = $user->can('invite', $team) || $user->can('update', $team);

                    $team->setAttribute('can_manage', $user->can('update', $team));
                    $team->setAttribute('can_remove_members', $this->canRemoveTeamMembers($user, $team));
                    $team->setAttribute('can_delete', $user->can('delete', $team));
                    $team->setAttribute('viewer_is_member', $viewerIsMember);
                    $team->setAttribute('viewer_pending_join_request_id', $pendingJoinRequest?->id);
                    $team->setAttribute('can_request_join', $canSeeClubTeams && ! $viewerIsMember && ! $pendingJoinRequest);
                    $team->setAttribute('pending_join_requests', $canHandleJoinRequests
                        ? $team->joinRequests
                            ->map(fn (TeamJoinRequest $joinRequest) => [
                                'id' => $joinRequest->id,
                                'created_at' => $joinRequest->created_at,
                                'user' => $joinRequest->user,
                            ])
                            ->values()
                        : collect());
                });
                $club->setRelation('teams', $teams);
            });
        $receivedInvitations = TeamInvitation::query()
            ->where('recipient_id', $user->id)
            ->where('status', 'pending')
            ->with(['team.club:id,name', 'inviter:id,name,email'])
            ->latest('id')
            ->get()
            ->map(fn (TeamInvitation $invitation) => [
                'id' => $invitation->id,
                'role' => $invitation->role,
                'created_at' => $invitation->created_at,
                'team' => [
                    'id' => $invitation->team?->id,
                    'name' => $invitation->team?->name,
                    'sport_type' => $invitation->team?->sport_type,
                    'club' => $invitation->team?->club,
                ],
                'inviter' => $invitation->inviter,
            ]);

        return Inertia::render('Auth/Dashboard/Teams/Index', [
            'clubs' => $clubs,
            'receivedInvitations' => $receivedInvitations,
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
            $user->can('update', $club),
            403
        );

        if (! Team::query()->where('club_id', $club->id)->where('name', $data['name'])->exists()) {
            $this->planFeatures->ensureCanCreateTeam($club);
        }

        $team = DB::transaction(function () use ($club, $data, $user) {
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

            $this->syncTeamChatMembers($team, [$user->id]);

            return $team;
        });

        $this->gamification->grantToTeam($user, $team, 'team_member_joined', $team, [
            'member_id' => $user->id,
            'role' => 'Coach',
        ]);

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
                'gamification' => $this->gamification->summaryFor($team, 'team'),
                'badges' => UserBadge::query()
                    ->where('awardable_type', Team::class)
                    ->where('awardable_id', $team->id)
                    ->with('badge:id,key,name,description,icon')
                    ->latest('id')
                    ->limit(12)
                    ->get()
                    ->pluck('badge')
                    ->values(),
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

            $this->planFeatures->ensureCanSendMemberInvitations($team->club);

            $invitation = TeamInvitation::updateOrCreate(
                ['team_id' => $team->id, 'recipient_id' => $recipient->id],
                [
                    'inviter_id' => $request->user()->id,
                    'email' => $recipient->email,
                    'token' => Str::random(64),
                    'role' => $data['role'],
                    'status' => 'pending',
                    'invited_at' => now(),
                    'responded_at' => null,
                ],
            );

            AppNotification::send($recipient->id, $data['role'] === 'Coach' ? 'team.trainer_mentioned' : 'team.invite', [
                'title' => $data['role'] === 'Coach' ? 'Trainer-Einladung zu '.$team->name : 'Einladung zu '.$team->name,
                'body' => $data['role'] === 'Coach'
                    ? 'Du wurdest als Trainer für '.$team->name.' eingeladen.'
                    : 'Du wurdest als '.$data['role'].' eingeladen.',
                'url' => route('auth.teams.index', ['team_invitation' => $invitation->id]),
                'team_id' => $team->id,
                'invitation_id' => $invitation->id,
            ]);

            return back()->with('success', 'Einladung gesendet.');
        }

        $email = strtolower($data['email']);
        $this->planFeatures->ensureCanSendMemberInvitations($team->club);

        $invitation = TeamInvitation::updateOrCreate(
            ['team_id' => $team->id, 'email' => $email],
            [
                'inviter_id' => $request->user()->id,
                'recipient_id' => null,
                'token' => Str::random(64),
                'role' => $data['role'],
                'status' => 'pending',
                'invited_at' => null,
                'responded_at' => null,
            ],
        );

        try {
            Notification::route('mail', $email)->notify(new ExternalTeamInvitation($invitation->load('team')));
            $invitation->forceFill(['invited_at' => now()])->save();
        } catch (Throwable $exception) {
            Log::warning('External team invitation mail failed.', [
                'team_id' => $team->id,
                'club_id' => $team->club_id,
                'email' => $email,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'email' => 'Die Einladung wurde vorbereitet, aber die E-Mail konnte nicht versendet werden. Bitte pruefe die SMTP-/Mail-Einstellungen oder versuche es spaeter erneut.',
            ]);
        }

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

            $this->syncTeamChatMembers($invitation->team, [$invitation->recipient_id]);

            $invitation->team->club->users()->syncWithoutDetaching([
                $invitation->recipient_id => [
                    'role' => 'member',
                    'roles' => ['member'],
                    'membership_status' => 'non_member',
                    'joined_on' => now()->toDateString(),
                ],
            ]);

            $invitation->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);
        });

        $this->gamification->grantToTeam($request->user(), $invitation->team, 'team_member_joined', $invitation, [
            'member_id' => $request->user()->id,
            'role' => $invitation->role,
        ]);

        return back()->with('success', 'Einladung angenommen.');
    }

    public function declineInvitation(Request $request, TeamInvitation $invitation)
    {
        abort_unless($invitation->recipient_id === $request->user()->id, 403);
        abort_unless($invitation->status === 'pending', 422);

        $invitation->update([
            'status' => 'declined',
            'responded_at' => now(),
        ]);

        return back()->with('success', 'Einladung abgelehnt.');
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

            $this->syncTeamChatMembers($invitation->team, [$request->user()->id]);

            $invitation->team->club->users()->syncWithoutDetaching([
                $request->user()->id => [
                    'role' => 'member',
                    'roles' => ['member'],
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

        $this->gamification->grantToTeam($request->user(), $invitation->team, 'team_member_joined', $invitation, [
            'member_id' => $request->user()->id,
            'role' => $invitation->role,
        ]);

        return redirect()->route('auth.teams.index')->with('success', 'Einladung angenommen.');
    }

    public function requestJoin(Request $request, Team $team)
    {
        $this->authorize('view', $team);

        if (! $team->club->users()->where('users.id', $request->user()->id)->exists()) {
            throw ValidationException::withMessages([
                'team' => 'Du musst Mitglied im Verein sein, bevor du einem Team beitreten kannst.',
            ]);
        }

        if ($team->users()->where('users.id', $request->user()->id)->exists()) {
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

        $team->load('club.users');

        $team->club->users
            ->filter(fn (User $member) => filled(array_intersect($member->pivot?->roles ?: [$member->pivot?->role], ['owner', 'admin', 'manager'])))
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

        if ($joinRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'join_request' => 'Diese Team-Beitrittsanfrage wurde bereits bearbeitet.',
            ]);
        }

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

            $this->syncTeamChatMembers($joinRequest->team, [$joinRequest->user_id]);

            $joinRequest->team->club->users()->syncWithoutDetaching([
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

        $this->gamification->grantToTeam($request->user(), $joinRequest->team, 'team_member_joined', $joinRequest, [
            'member_id' => $joinRequest->user_id,
            'role' => $data['role'] ?? 'Player',
        ]);

        AppNotification::send($joinRequest->user_id, 'team.join_request_accepted', [
            'title' => 'Team-Beitrittsanfrage akzeptiert',
            'body' => 'Deine Anfrage für '.$joinRequest->team->name.' wurde akzeptiert.',
            'url' => route('auth.teams.show', $joinRequest->team),
            'team_id' => $joinRequest->team_id,
            'club_id' => $joinRequest->team->club_id,
        ]);

        if (($data['role'] ?? 'Player') === 'Coach') {
            AppNotification::send($joinRequest->user_id, 'team.trainer_mentioned', [
                'title' => 'Als Trainer aufgenommen',
                'body' => 'Du wurdest in '.$joinRequest->team->name.' als Trainer aufgenommen.',
                'url' => route('auth.teams.index'),
                'team_id' => $joinRequest->team_id,
                'club_id' => $joinRequest->team->club_id,
            ]);
        }

        return back()->with('success', 'Beitrittsanfrage angenommen.');
    }

    public function declineJoinRequest(Request $request, TeamJoinRequest $joinRequest)
    {
        $this->authorize('update', $joinRequest->team);

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
            'body' => 'Deine Anfrage für '.$joinRequest->team->name.' wurde abgelehnt.',
            'url' => route('auth.teams.show', $joinRequest->team),
            'team_id' => $joinRequest->team_id,
            'club_id' => $joinRequest->team->club_id,
        ]);

        return back()->with('success', 'Beitrittsanfrage abgelehnt.');
    }

    public function updateMember(Request $request, Team $team, User $user)
    {
        $this->authorize('update', $team);

        $data = $request->validate([
            'role' => ['required', Rule::in(Team::ROLES)],
        ]);

        $previousRole = $team->users()
            ->where('users.id', $user->id)
            ->first()?->pivot?->role;

        $team->users()->updateExistingPivot($user->id, [
            'role' => $data['role'],
        ]);

        if ($data['role'] === 'Coach' && $previousRole !== 'Coach') {
            AppNotification::send($user, 'team.trainer_mentioned', [
                'title' => 'Als Trainer eingetragen',
                'body' => 'Du wurdest in '.$team->name.' als Trainer eingetragen.',
                'url' => route('auth.teams.index'),
                'team_id' => $team->id,
                'club_id' => $team->club_id,
            ]);
        }

        return back()->with('success', 'Teamrolle aktualisiert.');
    }

    public function removeMember(Request $request, Team $team, User $user)
    {
        $isLeavingSelf = $request->user()->id === $user->id;
        $data = $isLeavingSelf
            ? $request->validate([
                'reason' => ['nullable', 'string', 'max:1000'],
            ])
            : [];

        if (! $isLeavingSelf) {
            abort_unless($this->canRemoveTeamMembers($request->user(), $team), 403);
        }

        abort_unless($team->users()->where('users.id', $user->id)->exists(), 404);

        if ($isLeavingSelf) {
            $hasOpenDebt = Invoice::query()
                ->where('club_id', $team->club_id)
                ->where('user_id', $user->id)
                ->whereIn('status', ['open', 'overdue'])
                ->exists();

            if ($hasOpenDebt) {
                throw ValidationException::withMessages([
                    'team' => 'Du kannst das Team erst verlassen, wenn alle offenen Rechnungen im Verein ausgeglichen sind.',
                ]);
            }
        }

        $team->users()->detach($user->id);
        $this->removeTeamChatMember($team, $user->id);

        if ($isLeavingSelf) {
            $this->notifyClubManagers($team->club, 'team.member_left', [
                'title' => 'Mitglied hat Team verlassen',
                'body' => $user->name.' hat '.$team->name.' verlassen.'
                    .(filled($data['reason'] ?? null) ? "\n\nBegruendung: ".$data['reason'] : ''),
                'url' => route('auth.teams.index'),
                'club_id' => $team->club_id,
                'team_id' => $team->id,
                'user_id' => $user->id,
            ], $user->id);
        } else {
            AppNotification::send($user, 'team.member_removed', [
                'title' => 'Aus Team entfernt',
                'body' => 'Du wurdest aus '.$team->name.' entfernt. Deine Vereinsmitgliedschaft bleibt bestehen.',
                'url' => route('auth.notifications.index'),
                'club_id' => $team->club_id,
                'team_id' => $team->id,
            ]);
        }

        return back()->with('success', $isLeavingSelf ? 'Du hast das Team verlassen.' : 'Mitglied entfernt.');
    }

    private function notifyClubManagers(Club $club, string $type, array $data, ?int $exceptUserId = null): void
    {
        $club->users()
            ->tap(fn ($query) => ClubRoles::whereAny($query, ['owner', 'admin', 'manager']))
            ->when($exceptUserId, fn ($query) => $query->where('users.id', '!=', $exceptUserId))
            ->get(['users.id'])
            ->each(fn (User $manager) => AppNotification::send($manager, $type, $data));
    }

    private function canRemoveTeamMembers(User $user, Team $team): bool
    {
        if ($user->can('delete', $team->club)) {
            return true;
        }

        $managesClubMembers = $team->club
            ->users()
            ->where('users.id', $user->id)
            ->tap(fn ($query) => ClubRoles::whereAny($query, ['owner', 'admin', 'manager', 'academy_manager']))
            ->exists();

        return $managesClubMembers
            || ($user->can('team.kick') && $user->can('update', $team));
    }

    private function syncTeamChatMembers(Team $team, array $newUserIds = []): void
    {
        $participantIds = $team->users()
            ->pluck('users.id')
            ->merge($newUserIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($participantIds->isEmpty()) {
            return;
        }

        $conversation = Conversation::firstOrCreate(
            ['type' => 'team', 'team_id' => $team->id],
            ['club_id' => $team->club_id],
        );

        if (! $conversation->club_id && $team->club_id) {
            $conversation->update(['club_id' => $team->club_id]);
        }

        $existingIds = $conversation->users()
            ->whereIn('users.id', $participantIds)
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id);

        $missingIds = $participantIds->diff($existingIds)->values();

        if ($missingIds->isEmpty()) {
            return;
        }

        $joinedAt = now();

        $conversation->users()->syncWithoutDetaching(
            $missingIds
                ->mapWithKeys(fn (int $id) => [$id => ['joined_at' => $joinedAt]])
                ->all()
        );
    }

    private function removeTeamChatMember(Team $team, int $userId): void
    {
        Conversation::query()
            ->where('type', 'team')
            ->where('team_id', $team->id)
            ->each(fn (Conversation $conversation) => $conversation->users()->detach($userId));
    }

    private function canManageJobsForClub(User $user, Club $club): bool
    {
        return $user->hasAnyRole(Roles::FULL_ACCESS)
            || (
                $user->can('club.jobs.manage')
                && (
                    $club->owner_id === $user->id
                    || tap($club->users()->where('users.id', $user->id), fn ($query) => ClubRoles::whereAny($query, ['owner', 'admin', 'manager']))->exists()
                )
            );
    }
}
