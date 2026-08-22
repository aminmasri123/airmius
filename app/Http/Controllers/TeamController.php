<?php

namespace App\Http\Controllers;

use App\Http\Resources\Api\V1\TeamResource;
use App\Models\Club;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Post;
use App\Models\Sport;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamJoinRequest;
use App\Models\User;
use App\Models\UserBadge;
use App\Notifications\ExternalTeamInvitation;
use App\Services\GamificationService;
use App\Services\MediaOptimizer;
use App\Services\PlanFeatureService;
use App\Support\AppNotification;
use App\Support\ClubRoles;
use App\Support\Roles;
use App\Support\TeamRoles;
use App\Support\UploadStorage;
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
                'users:id,name,email,profile_photo_path',
                'sponsors' => fn ($query) => $query->latest('id'),
                'jobs' => fn ($query) => $query->with('sport:id,name,slug')->latest('id'),
                'teams' => fn ($query) => $query
                    ->withCount('users')
                    ->with([
                        'users:id,name',
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
            ->with(['team.club:id,name', 'inviter:id,name'])
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
            'teamRoles' => Team::ROLES,
            'clubRoles' => self::CLUB_MEMBER_ROLES,
            'filters' => $filters,
            'startClubOnboarding' => request()->boolean('create_club'),
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

        return back()->with('success', __('organization.team.created'));
    }

    public function update(Request $request, Team $team)
    {
        $this->authorize('update', $team);

        $previousName = $team->name;
        $previousSportType = $team->sport_type;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sport_type' => ['nullable', 'string', 'max:120'],
        ]);

        $team->update([
            'name' => $data['name'],
            'sport_type' => $data['sport_type'] ?? $team->sport_type,
        ]);

        if ($team->wasChanged(['name', 'sport_type'])) {
            $this->notifyTeamProfileUpdated($team->fresh(['users', 'club']), $request->user(), $previousName, $previousSportType);
        }

        return back()->with('success', __('organization.team.updated'));
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
                ->orderBy('name'),
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
                'comments' => fn ($query) => $query->where('moderation_status', 'approved'),
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
                'attendance_stats' => $this->canViewTrainingAttendanceStats($viewer, $team)
                    ? $this->trainingAttendanceStats($team)
                    : null,
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

    public function attendanceStats(Request $request, Team $team)
    {
        $this->authorize('view', $team);
        abort_unless($this->canViewTrainingAttendanceStats($request->user(), $team), 403);

        return response()->json([
            'data' => $this->trainingAttendanceStats($team),
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

        return back()->with('success', __('organization.team.images_updated'));
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

    public function destroy(Team $team)
    {
        $this->authorize('delete', $team);

        $team->delete();

        return back()->with('success', __('organization.team.deleted'));
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
                    'email' => __('organization.team.already_member'),
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

            $coachInvitation = $data['role'] === 'Coach';
            AppNotification::sendLocalized(
                $recipient,
                $coachInvitation ? 'team.trainer_mentioned' : 'team.invite',
                $coachInvitation
                    ? 'organization.notifications.team_coach_invite_title'
                    : 'organization.notifications.team_invite_title',
                $coachInvitation
                    ? 'organization.notifications.team_coach_invite_body'
                    : 'organization.notifications.team_invite_body',
                [
                    'team' => $team->name,
                    'role' => AppNotification::translatedReplacement(
                        'organization.roles.team.'.strtolower($data['role']),
                        TeamRoles::definition($data['role'])['label'] ?? $data['role'],
                    ),
                ],
                [
                    'url' => route('auth.teams.index', ['team_invitation' => $invitation->id]),
                    'team_id' => $team->id,
                    'team_name' => $team->name,
                    'club_id' => $team->club_id,
                    'club_name' => $team->club?->name,
                    'invitation_id' => $invitation->id,
                    'role' => $data['role'],
                    'inviter_id' => $request->user()->id,
                    'inviter_name' => $request->user()->name,
                    'inviter_email' => $request->user()->email,
                ],
            );

            return back()->with('success', __('organization.team.invitation_sent'));
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
                'email' => __('organization.team.email_invitation_failed'),
            ]);
        }

        return back()->with('success', __('organization.team.email_invitation_sent'));
    }

    public function acceptInvitation(Request $request, TeamInvitation $invitation)
    {
        abort_unless($invitation->recipient_id === $request->user()->id, 403);
        abort_unless($invitation->status === 'pending', 422);
        abort_if(
            ! $invitation->team->club->users()->where('users.id', $invitation->recipient_id)->exists()
            && ! $invitation->team->club->canAddMembers(),
            422,
            __('organization.team.club_member_limit_reached')
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

        $this->markTeamInvitationNotificationResponded($invitation->fresh(['team.club']), $request->user(), 'accepted');
        $this->notifyTeamInvitationResponse($invitation->fresh(['team.club', 'inviter']), $request->user(), 'accepted');

        return back()->with('success', __('organization.team.invitation_accepted'));
    }

    public function declineInvitation(Request $request, TeamInvitation $invitation)
    {
        abort_unless($invitation->recipient_id === $request->user()->id, 403);
        abort_unless($invitation->status === 'pending', 422);

        $invitation->update([
            'status' => 'declined',
            'responded_at' => now(),
        ]);

        $this->markTeamInvitationNotificationResponded($invitation->fresh(['team.club']), $request->user(), 'declined');
        $this->notifyTeamInvitationResponse($invitation->fresh(['team.club', 'inviter']), $request->user(), 'declined');

        return back()->with('success', __('organization.team.invitation_declined'));
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
            __('organization.team.club_member_limit_reached')
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

        $this->markTeamInvitationNotificationResponded($invitation->fresh(['team.club']), $request->user(), 'accepted');
        $this->notifyTeamInvitationResponse($invitation->fresh(['team.club', 'inviter']), $request->user(), 'accepted');

        return redirect()->route('auth.teams.index')->with('success', __('organization.team.invitation_accepted'));
    }

    public function requestJoin(Request $request, Team $team)
    {
        $this->authorize('view', $team);

        if (! $team->club->users()->where('users.id', $request->user()->id)->exists()) {
            throw ValidationException::withMessages([
                'team' => __('organization.team.club_membership_required'),
            ]);
        }

        if ($team->users()->where('users.id', $request->user()->id)->exists()) {
            throw ValidationException::withMessages([
                'team' => __('organization.team.already_member'),
            ]);
        }

        $existingRequest = TeamJoinRequest::query()
            ->where('team_id', $team->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($existingRequest?->status === 'pending') {
            throw ValidationException::withMessages([
                'team' => __('organization.team.join_request_pending'),
            ]);
        }

        $joinRequest = TeamJoinRequest::updateOrCreate(
            ['team_id' => $team->id, 'user_id' => $request->user()->id],
            ['status' => 'pending', 'responded_at' => null],
        );

        $team->load('club.users');

        $team->club->users
            ->filter(fn (User $member) => filled(array_intersect($member->pivot?->roles ?: [$member->pivot?->role], ['owner', 'admin', 'manager'])))
            ->each(fn (User $member) => AppNotification::sendLocalized(
                $member,
                'team.join_request',
                'organization.notifications.team_join_request_title',
                'organization.notifications.team_join_request_body',
                ['user' => $request->user()->name, 'team' => $team->name],
                [
                    'url' => route('auth.teams.index', [
                        'team' => $team->id,
                        'team_join_request' => $joinRequest->id,
                    ]),
                    'team_id' => $team->id,
                    'join_request_id' => $joinRequest->id,
                ],
            ));

        return back()->with('success', __('organization.team.join_request_sent'));
    }

    public function approveJoinRequest(Request $request, TeamJoinRequest $joinRequest)
    {
        $this->authorize('invite', $joinRequest->team);

        if ($joinRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'join_request' => __('organization.team.join_request_closed'),
            ]);
        }

        $data = $request->validate([
            'role' => ['nullable', Rule::in(Team::ROLES)],
        ]);
        abort_if(
            ! $joinRequest->team->club->users()->where('users.id', $joinRequest->user_id)->exists()
            && ! $joinRequest->team->club->canAddMembers(),
            422,
            __('organization.team.club_member_limit_reached')
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

        AppNotification::sendLocalized(
            $joinRequest->user_id,
            'team.join_request_accepted',
            'organization.notifications.team_join_accepted_title',
            'organization.notifications.team_join_accepted_body',
            ['team' => $joinRequest->team->name],
            [
                'url' => route('auth.teams.show', $joinRequest->team),
                'team_id' => $joinRequest->team_id,
                'club_id' => $joinRequest->team->club_id,
            ],
        );

        if (($data['role'] ?? 'Player') === 'Coach') {
            AppNotification::sendLocalized(
                $joinRequest->user_id,
                'team.trainer_mentioned',
                'organization.notifications.team_coach_joined_title',
                'organization.notifications.team_coach_joined_body',
                ['team' => $joinRequest->team->name],
                [
                    'url' => route('auth.teams.index'),
                    'team_id' => $joinRequest->team_id,
                    'club_id' => $joinRequest->team->club_id,
                ],
            );
        }

        if ($request->expectsJson()) {
            return new TeamResource($joinRequest->team->fresh()->load(['club.users', 'users', 'joinRequests.user'])->loadCount(['users', 'events']));
        }

        return back()->with('success', __('organization.team.join_request_approved'));
    }

    public function declineJoinRequest(Request $request, TeamJoinRequest $joinRequest)
    {
        $this->authorize('update', $joinRequest->team);

        if ($joinRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'join_request' => __('organization.team.join_request_closed'),
            ]);
        }

        $joinRequest->update([
            'status' => 'declined',
            'responded_at' => now(),
        ]);

        AppNotification::sendLocalized(
            $joinRequest->user_id,
            'team.join_request_declined',
            'organization.notifications.team_join_declined_title',
            'organization.notifications.team_join_declined_body',
            ['team' => $joinRequest->team->name],
            [
                'url' => route('auth.teams.show', $joinRequest->team),
                'team_id' => $joinRequest->team_id,
                'club_id' => $joinRequest->team->club_id,
            ],
        );

        if ($request->expectsJson()) {
            return new TeamResource($joinRequest->team->fresh()->load(['club.users', 'users', 'joinRequests.user'])->loadCount(['users', 'events']));
        }

        return back()->with('success', __('organization.team.join_request_declined'));
    }

    public function storeMember(Request $request, Team $team)
    {
        $this->authorize('invite', $team);

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['required', Rule::in(Team::ROLES)],
        ]);

        $member = User::findOrFail($data['user_id']);

        if (! $team->club->users()->where('users.id', $member->id)->exists()) {
            throw ValidationException::withMessages([
                'user_id' => __('organization.team.club_member_required'),
            ]);
        }

        if ($team->users()->where('users.id', $member->id)->exists()) {
            throw ValidationException::withMessages([
                'user_id' => __('organization.team.member_already_added'),
            ]);
        }

        DB::transaction(function () use ($team, $member, $data) {
            $team->users()->syncWithoutDetaching([
                $member->id => ['role' => $data['role']],
            ]);

            $this->syncTeamChatMembers($team, [$member->id]);
        });

        AppNotification::sendLocalized(
            $member,
            'team.member_added',
            'organization.notifications.team_member_added_title',
            'organization.notifications.team_member_added_body',
            ['team' => $team->name],
            [
                'url' => route('auth.teams.show', $team),
                'club_id' => $team->club_id,
                'team_id' => $team->id,
                'role' => $data['role'],
                'added_by' => $request->user()->id,
            ],
        );

        if ($request->expectsJson()) {
            return new TeamResource($team->fresh()->load(['club.users', 'users', 'joinRequests.user'])->loadCount(['users', 'events']));
        }

        return back()->with('success', __('organization.team.member_added'));
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

        $newRole = $data['role'];

        $team->users()->updateExistingPivot($user->id, [
            'role' => $newRole,
        ]);

        if ($previousRole !== $newRole) {
            $previousRoleLabel = filled($previousRole)
                ? TeamRoles::definition($previousRole)['label']
                : 'Unbekannt';
            $newRoleLabel = TeamRoles::definition($newRole)['label'] ?? $newRole;

            AppNotification::sendLocalized(
                $user,
                'team.role_updated',
                'organization.notifications.team_role_title',
                'organization.notifications.team_role_body',
                [
                    'team' => $team->name,
                    'previous' => AppNotification::translatedReplacement(
                        $previousRole ? 'organization.roles.team.'.strtolower($previousRole) : 'organization.roles.unknown',
                        $previousRoleLabel,
                    ),
                    'next' => AppNotification::translatedReplacement(
                        'organization.roles.team.'.strtolower($newRole),
                        $newRoleLabel,
                    ),
                ],
                [
                    'url' => route('auth.teams.show', $team),
                    'team_id' => $team->id,
                    'club_id' => $team->club_id,
                    'previous_role' => $previousRole,
                    'role' => $newRole,
                ],
            );
        }

        if ($request->expectsJson()) {
            return new TeamResource($team->fresh()->load(['club.users', 'users', 'joinRequests.user'])->loadCount(['users', 'events']));
        }

        return back()->with('success', __('organization.team.role_updated'));
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
                    'team' => __('organization.team.open_invoices_before_leaving'),
                ]);
            }
        }

        $team->users()->detach($user->id);
        $this->removeTeamChatMember($team, $user->id);

        if ($isLeavingSelf) {
            $this->notifyClubManagersLocalized(
                $team->club,
                'team.member_left',
                'organization.notifications.team_member_left_title',
                'organization.notifications.team_member_left_body',
                [
                    'user' => $user->name,
                    'team' => $team->name,
                    'reason' => filled($data['reason'] ?? null) ? "\n\n".$data['reason'] : '',
                ],
                [
                    'url' => route('auth.teams.index'),
                    'club_id' => $team->club_id,
                    'team_id' => $team->id,
                    'user_id' => $user->id,
                ],
                $user->id,
            );
        } else {
            AppNotification::sendLocalized(
                $user,
                'team.member_removed',
                'organization.notifications.team_member_removed_title',
                'organization.notifications.team_member_removed_body',
                ['team' => $team->name],
                [
                    'url' => route('auth.notifications.index'),
                    'club_id' => $team->club_id,
                    'team_id' => $team->id,
                ],
            );
        }

        return back()->with('success', $isLeavingSelf
            ? __('organization.team.left')
            : __('organization.team.member_removed'));
    }

    private function notifyTeamProfileUpdated(Team $team, User $actor, string $previousName, ?string $previousSportType): void
    {
        $team->users
            ->where('id', '!=', $actor->id)
            ->each(fn (User $member) => AppNotification::sendLocalized(
                $member,
                'team.profile_updated',
                'organization.notifications.team_profile_updated_title',
                'organization.notifications.team_profile_updated_body',
                ['user' => $actor->name, 'team' => $team->name, 'changes' => ''],
                [
                    'url' => route('auth.teams.show', $team),
                    'club_id' => $team->club_id,
                    'team_id' => $team->id,
                    'updated_by' => $actor->id,
                    'previous_name' => $previousName,
                    'name' => $team->name,
                    'previous_sport_type' => $previousSportType,
                    'sport_type' => $team->sport_type,
                ],
            ));
    }

    private function notifyTeamInvitationResponse(TeamInvitation $invitation, User $responder, string $status): void
    {
        if (! $invitation->inviter_id || $invitation->inviter_id === $responder->id) {
            return;
        }

        $accepted = $status === 'accepted';
        $teamName = $invitation->team?->name ?? 'Team';

        AppNotification::sendLocalized(
            $invitation->inviter_id,
            'team.invitation.'.$status,
            $accepted
                ? 'organization.notifications.team_invitation_accepted_title'
                : 'organization.notifications.team_invitation_declined_title',
            $accepted
                ? 'organization.notifications.team_invitation_accepted_body'
                : 'organization.notifications.team_invitation_declined_body',
            ['user' => $responder->name, 'team' => $teamName],
            [
                'url' => route('auth.teams.index', ['team' => $invitation->team_id]),
                'team_id' => $invitation->team_id,
                'team_name' => $teamName,
                'club_id' => $invitation->team?->club_id,
                'club_name' => $invitation->team?->club?->name,
                'invitation_id' => $invitation->id,
                'invitation_status' => $status,
                'role' => $invitation->role,
                'responder_id' => $responder->id,
                'responder_name' => $responder->name,
                'responder_email' => $responder->email,
            ],
        );
    }

    private function markTeamInvitationNotificationResponded(TeamInvitation $invitation, User $responder, string $status): void
    {
        $accepted = $status === 'accepted';
        $teamName = $invitation->team?->name ?? 'Team';
        $localized = AppNotification::localizedData(
            $responder,
            $accepted
                ? 'organization.notifications.team_invitation_accepted_title'
                : 'organization.notifications.team_invitation_declined_title',
            $accepted
                ? 'organization.notifications.team_invitation_self_accepted_body'
                : 'organization.notifications.team_invitation_self_declined_body',
            ['team' => $teamName],
        ) ?? [];

        \App\Models\Notification::query()
            ->where('user_id', $responder->id)
            ->where('data->invitation_id', $invitation->id)
            ->get()
            ->each(function (\App\Models\Notification $notification) use ($invitation, $status, $localized, $teamName) {
                $data = $notification->data ?: [];
                $notification->update([
                    'read' => false,
                    'data' => array_merge($data, [
                        ...$localized,
                        'team_id' => $invitation->team_id,
                        'team_name' => $teamName,
                        'club_id' => $invitation->team?->club_id,
                        'club_name' => $invitation->team?->club?->name,
                        'invitation_id' => $invitation->id,
                        'invitation_status' => $status,
                        'role' => $invitation->role,
                        'responded_at' => now()->toJSON(),
                    ]),
                ]);
            });
    }

    private function notifyClubManagersLocalized(
        Club $club,
        string $type,
        string $titleKey,
        ?string $bodyKey,
        array $replace,
        array $data,
        ?int $exceptUserId = null,
    ): void {
        $club->users()
            ->tap(fn ($query) => ClubRoles::whereAny($query, ['owner', 'admin', 'manager']))
            ->when($exceptUserId, fn ($query) => $query->where('users.id', '!=', $exceptUserId))
            ->get(['users.id', 'users.language'])
            ->each(fn (User $manager) => AppNotification::sendLocalized(
                $manager,
                $type,
                $titleKey,
                $bodyKey,
                $replace,
                $data,
            ));
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
