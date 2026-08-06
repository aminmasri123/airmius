<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TeamResource;
use App\Models\Club;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamJoinRequest;
use App\Models\User;
use App\Notifications\ExternalTeamInvitation;
use App\Services\PlanFeatureService;
use App\Support\AppNotification;
use App\Support\ClubRoles;
use App\Support\Roles;
use App\Support\TeamRoles;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

class TeamController extends Controller
{
    use AuthorizesRequests;

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

    public function storeWithToken(Request $request)
    {
        $token = (string) ($request->bearerToken() ?: $request->input('token', ''));
        $accessToken = $token !== '' ? PersonalAccessToken::findToken($token) : null;
        $user = $accessToken?->tokenable;

        abort_unless($user instanceof User, 401, 'Nicht authentifiziert. Bitte in Flutter abmelden und neu einloggen.');

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);
        $request->merge([
            'club_id' => $request->input('club_id'),
            'name' => $request->input('name'),
            'sport_type' => $request->input('sport_type'),
        ]);

        return $this->store($request);
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
            'body' => 'Deine Anfrage für '.$team->name.' wurde akzeptiert.',
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
            'body' => 'Deine Anfrage für '.$team->name.' wurde abgelehnt.',
            'team_id' => $team->id,
            'club_id' => $team->club_id,
        ]);

        return new TeamResource($team->fresh()->load(['club.users', 'users', 'joinRequests.user'])->loadCount(['users', 'events']));
    }

    public function declineJoinRequestById(Request $request, TeamJoinRequest $joinRequest)
    {
        return $this->declineJoinRequest($request, $joinRequest->team, $joinRequest);
    }

    public function invite(Request $request, Team $team)
    {
        $this->authorize('invite', $team);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(Team::ROLES)],
        ]);

        $recipient = User::where('email', strtolower($data['email']))->first();

        $this->planFeatures->ensureCanSendMemberInvitations($team->club);

        if (! $recipient) {
            $email = strtolower($data['email']);
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
                Log::warning('External team invitation mail failed via API.', [
                    'team_id' => $team->id,
                    'club_id' => $team->club_id,
                    'email' => $email,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);

                throw ValidationException::withMessages([
                    'email' => 'Die Einladung wurde vorbereitet, aber die E-Mail konnte nicht versendet werden. Bitte prüfe die SMTP-/Mail-Einstellungen oder versuche es später erneut.',
                ]);
            }

            return response()->json([
                'data' => $this->teamInvitationPayload($invitation->fresh(['team.club:id,name', 'inviter:id,name,email'])),
                'message' => 'Einladung per E-Mail wurde gesendet.',
            ], 201);
        }

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
                'invited_at' => now(),
                'responded_at' => null,
            ],
        );

        AppNotification::send($recipient->id, $data['role'] === 'Coach' ? 'team.trainer_mentioned' : 'team.invite', [
            'title' => $data['role'] === 'Coach' ? 'Trainer-Einladung zu '.$team->name : 'Einladung zu '.$team->name,
            'body' => $data['role'] === 'Coach'
                ? 'Du wurdest als Trainer für '.$team->name.' eingeladen.'
                : 'Du wurdest als '.$data['role'].' eingeladen.',
            'url' => '/teams?team_invitation='.$invitation->id,
            'team_id' => $team->id,
            'team_name' => $team->name,
            'club_id' => $team->club_id,
            'club_name' => $team->club?->name,
            'invitation_id' => $invitation->id,
            'role' => $data['role'],
            'inviter_id' => $request->user()->id,
            'inviter_name' => $request->user()->name,
            'inviter_email' => $request->user()->email,
        ]);

        return response()->json([
            'data' => $this->teamInvitationPayload($invitation->fresh(['team.club:id,name', 'inviter:id,name,email'])),
            'message' => 'Einladung wurde gesendet.',
        ], 201);
    }

    public function invitations(Request $request)
    {
        $invitations = TeamInvitation::query()
            ->where('recipient_id', $request->user()->id)
            ->where('status', 'pending')
            ->with(['team.club:id,name', 'inviter:id,name,email'])
            ->latest('id')
            ->get()
            ->map(fn (TeamInvitation $invitation) => $this->teamInvitationPayload($invitation))
            ->values();

        return response()->json(['data' => $invitations]);
    }

    public function invitation(Request $request, TeamInvitation $invitation)
    {
        abort_unless($invitation->recipient_id === $request->user()->id, 404);

        return response()->json([
            'data' => $this->teamInvitationPayload($invitation->loadMissing(['team.club:id,name', 'inviter:id,name,email'])),
        ]);
    }

    public function invitationByToken(Request $request, string $token)
    {
        $invitation = $this->pendingInvitationByToken($token);
        $this->assertInvitationTokenRecipient($request, $invitation);

        return response()->json([
            'data' => $this->teamInvitationPayload($invitation),
        ]);
    }

    public function acceptInvitationByToken(Request $request, string $token)
    {
        $invitation = $this->pendingInvitationByToken($token);
        $this->assertInvitationTokenRecipient($request, $invitation);
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

        $this->markTeamInvitationNotificationResponded(
            $invitation->fresh(['team.club']),
            $request->user(),
            'accepted'
        );
        $this->notifyTeamInvitationResponse(
            $invitation->fresh(['team.club', 'inviter']),
            $request->user(),
            'accepted'
        );

        return new TeamResource(
            $invitation->team->fresh()
                ->load(['club.users', 'users', 'joinRequests.user'])
                ->loadCount(['users', 'events'])
        );
    }

    public function declineInvitationByToken(Request $request, string $token)
    {
        $invitation = $this->pendingInvitationByToken($token);
        $this->assertInvitationTokenRecipient($request, $invitation);

        $invitation->update([
            'recipient_id' => $request->user()->id,
            'status' => 'declined',
            'responded_at' => now(),
        ]);

        $this->markTeamInvitationNotificationResponded(
            $invitation->fresh(['team.club']),
            $request->user(),
            'declined'
        );
        $this->notifyTeamInvitationResponse(
            $invitation->fresh(['team.club', 'inviter']),
            $request->user(),
            'declined'
        );

        return response()->json([
            'data' => $this->teamInvitationPayload(
                $invitation->fresh()->load(['team.club:id,name', 'inviter:id,name,email'])
            ),
        ]);
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

        $this->markTeamInvitationNotificationResponded($invitation->fresh(['team.club']), $request->user(), 'accepted');
        $this->notifyTeamInvitationResponse($invitation->fresh(['team.club', 'inviter']), $request->user(), 'accepted');

        return new TeamResource($invitation->team->fresh()->load(['club.users', 'users', 'joinRequests.user'])->loadCount(['users', 'events']));
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

        return response()->json([
            'data' => $this->teamInvitationPayload($invitation->fresh()->load(['team.club:id,name', 'inviter:id,name,email'])),
        ]);
    }

    public function updateMember(Request $request, Team $team, User $user)
    {
        $this->authorize('update', $team);
        abort_unless($team->users()->where('users.id', $user->id)->exists(), 404);

        $data = $request->validate([
            'role' => ['required', Rule::in(Team::ROLES)],
        ]);

        $previousRole = $team->users()
            ->where('users.id', $user->id)
            ->first()?->pivot?->role;

        $team->users()->updateExistingPivot($user->id, [
            'role' => $data['role'],
        ]);

        if ($previousRole !== $data['role']) {
            $previousRoleLabel = filled($previousRole)
                ? TeamRoles::definition($previousRole)['label']
                : 'Unbekannt';
            $newRoleLabel = TeamRoles::definition($data['role'])['label'] ?? $data['role'];

            AppNotification::send($user, 'team.role_updated', [
                'title' => 'Teamrolle geändert',
                'body' => 'Deine Rolle in '.$team->name.' wurde von '.$previousRoleLabel.' auf '.$newRoleLabel.' geändert.',
                'url' => '/notifications',
                'mobile_url' => 'airmius://teams/'.$team->id,
                'team_id' => $team->id,
                'club_id' => $team->club_id,
                'previous_role' => $previousRole,
                'role' => $data['role'],
            ]);
        }

        return new TeamResource($team->fresh()->load(['club.users', 'users', 'joinRequests.user'])->loadCount(['users', 'events']));
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
                'user_id' => 'Dieses Mitglied muss zuerst im Verein angelegt oder eingeladen werden.',
            ]);
        }

        if ($team->users()->where('users.id', $member->id)->exists()) {
            throw ValidationException::withMessages([
                'user_id' => 'Mitglied ist bereits im Team.',
            ]);
        }

        DB::transaction(function () use ($team, $member, $data) {
            $team->users()->syncWithoutDetaching([
                $member->id => ['role' => $data['role']],
            ]);

            $this->syncTeamChatMembers($team, [$member->id]);
        });

        AppNotification::send($member->id, 'team.member_added', [
            'title' => 'Zum Team hinzugefügt',
            'body' => 'Du wurdest zu '.$team->name.' hinzugefügt.',
            'url' => '/teams/'.$team->id,
            'club_id' => $team->club_id,
            'team_id' => $team->id,
            'role' => $data['role'],
            'added_by' => $request->user()->id,
        ]);

        return (new TeamResource($team->fresh()->load(['club.users', 'users', 'joinRequests.user'])->loadCount(['users', 'events'])))
            ->response()
            ->setStatusCode(201);
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
                    .(filled($data['reason'] ?? null) ? "\n\nBegründung: ".$data['reason'] : ''),
                'url' => '/teams/'.$team->id,
                'club_id' => $team->club_id,
                'team_id' => $team->id,
                'user_id' => $user->id,
            ], $user->id);
        } else {
            AppNotification::send($user, 'team.member_removed', [
                'title' => 'Aus Team entfernt',
                'body' => 'Du wurdest aus '.$team->name.' entfernt. Deine Vereinsmitgliedschaft bleibt bestehen.',
                'url' => '/notifications',
                'club_id' => $team->club_id,
                'team_id' => $team->id,
            ]);
        }

        return new TeamResource($team->fresh()->load(['club.users', 'users', 'joinRequests.user'])->loadCount(['users', 'events']));
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

    private function notifyClubManagers(Club $club, string $type, array $data, ?int $exceptUserId = null): void
    {
        $club->users()
            ->tap(fn ($query) => ClubRoles::whereAny($query, ['owner', 'admin', 'manager']))
            ->when($exceptUserId, fn ($query) => $query->where('users.id', '!=', $exceptUserId))
            ->get(['users.id'])
            ->each(fn (User $manager) => AppNotification::send($manager, $type, $data));
    }

    private function teamInvitationPayload(TeamInvitation $invitation): array
    {
        return [
            'id' => $invitation->id,
            'role' => $invitation->role,
            'status' => $invitation->status,
            'created_at' => $invitation->created_at?->toJSON(),
            'team' => [
                'id' => $invitation->team?->id,
                'club_id' => $invitation->team?->club_id,
                'name' => $invitation->team?->name,
                'sport_type' => $invitation->team?->sport_type,
                'club' => $invitation->team?->club ? [
                    'id' => $invitation->team->club->id,
                    'name' => $invitation->team->club->name,
                ] : null,
            ],
            'inviter' => $invitation->inviter ? [
                'id' => $invitation->inviter->id,
                'name' => $invitation->inviter->name,
                'email' => $invitation->inviter->email,
            ] : null,
        ];
    }

    private function pendingInvitationByToken(string $token): TeamInvitation
    {
        return TeamInvitation::query()
            ->where('token', $token)
            ->where('status', 'pending')
            ->with(['team.club', 'inviter:id,name,email'])
            ->firstOrFail();
    }

    private function assertInvitationTokenRecipient(
        Request $request,
        TeamInvitation $invitation
    ): void {
        abort_unless(
            filled($invitation->email) &&
                strtolower((string) $invitation->email) === strtolower((string) $request->user()->email),
            403
        );
    }

    private function notifyTeamInvitationResponse(TeamInvitation $invitation, User $responder, string $status): void
    {
        if (! $invitation->inviter_id || $invitation->inviter_id === $responder->id) {
            return;
        }

        $accepted = $status === 'accepted';
        $teamName = $invitation->team?->name ?? 'Team';

        AppNotification::send($invitation->inviter_id, 'team.invitation.'.$status, [
            'title' => $accepted ? 'Team-Einladung angenommen' : 'Team-Einladung abgelehnt',
            'body' => $responder->name.' hat die Einladung zu '.$teamName.($accepted ? ' angenommen.' : ' abgelehnt.'),
            'url' => '/teams/'.$invitation->team_id,
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
        ]);
    }

    private function markTeamInvitationNotificationResponded(TeamInvitation $invitation, User $responder, string $status): void
    {
        $accepted = $status === 'accepted';
        $teamName = $invitation->team?->name ?? 'Team';
        $title = $accepted ? 'Team-Einladung angenommen' : 'Team-Einladung abgelehnt';
        $body = $accepted
            ? 'Du hast die Einladung zu '.$teamName.' angenommen.'
            : 'Du hast die Einladung zu '.$teamName.' abgelehnt.';

        \App\Models\Notification::query()
            ->where('user_id', $responder->id)
            ->where('data->invitation_id', $invitation->id)
            ->get()
            ->each(function (\App\Models\Notification $notification) use ($invitation, $status, $title, $body, $teamName) {
                $data = $notification->data ?: [];
                $notification->update([
                    'read' => false,
                    'data' => array_merge($data, [
                        'title' => $title,
                        'body' => $body,
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

    private function notifyTeamProfileUpdated(Team $team, User $actor, string $previousName, ?string $previousSportType): void
    {
        $changes = [];
        if ($team->name !== $previousName) {
            $changes[] = 'Name: '.$previousName.' -> '.$team->name;
        }
        if (($team->sport_type ?? '') !== ($previousSportType ?? '')) {
            $changes[] = 'Sportart: '.($previousSportType ?: 'offen').' -> '.($team->sport_type ?: 'offen');
        }

        $body = $actor->name.' hat die Teamdaten von '.$team->name.' aktualisiert.';
        if ($changes !== []) {
            $body .= "\n".implode("\n", $changes);
        }

        $team->users
            ->where('id', '!=', $actor->id)
            ->each(fn (User $member) => AppNotification::send($member, 'team.profile_updated', [
                'title' => 'Teamdaten aktualisiert',
                'body' => $body,
                'url' => '/teams/'.$team->id,
                'club_id' => $team->club_id,
                'team_id' => $team->id,
                'updated_by' => $actor->id,
                'previous_name' => $previousName,
                'name' => $team->name,
                'previous_sport_type' => $previousSportType,
                'sport_type' => $team->sport_type,
            ]));
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
