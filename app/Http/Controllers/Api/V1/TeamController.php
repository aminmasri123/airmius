<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TeamResource;
use App\Models\Club;
use App\Models\ClubTrainingGroup;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamJoinRequest;
use App\Models\TeamTransferRequest;
use App\Models\User;
use App\Notifications\ExternalTeamInvitation;
use App\Services\PlanFeatureService;
use App\Support\AppNotification;
use App\Support\ClubAuditLog;
use App\Support\ClubPermissions;
use App\Support\Roles;
use App\Support\TeamRoles;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            ->with(['club.users', 'users', 'joinRequests.user', 'sportYearPeriod'])
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

        $team->loadMissing(['club.users', 'users', 'joinRequests.user', 'sportYearPeriod'])->loadCount(['users', 'events']);
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
                'team' => __('organization.team.club_membership_required'),
            ]);
        }

        if ($team->users->contains('id', $request->user()->id)) {
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

        $this->notifyTeamManagersLocalized(
            $team,
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
                'club_id' => $team->club_id,
                'join_request_id' => $joinRequest->id,
            ],
            $request->user()->id,
        );

        $freshTeam = $team->fresh()
            ->load(['club.users', 'users', 'joinRequests.user'])
            ->loadCount(['users', 'events']);
        $freshTeam->setAttribute('viewer_pending_join_request_id', $joinRequest->id);
        $freshTeam->setAttribute('can_request_join', false);

        return (new TeamResource($freshTeam))
            ->additional(['message' => __('organization.team.join_request_sent')])
            ->response()
            ->setStatusCode(201);
    }

    public function requestTransfer(Request $request, Team $team)
    {
        $this->authorize('view', $team);

        $team->loadMissing(['club', 'users']);

        $data = $request->validate([
            'source_team_id' => ['required', 'integer', Rule::exists('teams', 'id')->where('club_id', $team->club_id)],
            'role' => ['nullable', Rule::in(Team::ROLES)],
            'effective_on' => ['nullable', 'date'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $sourceTeam = Team::query()
            ->where('club_id', $team->club_id)
            ->findOrFail($data['source_team_id']);

        abort_if($sourceTeam->is($team), 422, __('organization.team.transfer_same_team'));
        abort_unless($sourceTeam->users()->where('users.id', $request->user()->id)->exists(), 403);
        abort_if($team->users()->where('users.id', $request->user()->id)->exists(), 422, __('organization.team.already_member'));

        $this->assertNoOpenTransfer($team, $request->user()->id);

        $transferRequest = TeamTransferRequest::query()->create([
            'club_id' => $team->club_id,
            'user_id' => $request->user()->id,
            'source_team_id' => $sourceTeam->id,
            'target_team_id' => $team->id,
            'role' => $data['role'] ?? TeamRoles::PLAYER,
            'status' => 'pending',
            'origin' => 'member_request',
            'effective_on' => $data['effective_on'] ?? now()->toDateString(),
            'message' => $data['message'] ?? null,
            'requested_by' => $request->user()->id,
        ]);

        ClubAuditLog::record($team->club, $request->user(), 'club.team_transfer.requested', $transferRequest, $this->transferAuditData($transferRequest));

        $this->notifyTeamManagersLocalized(
            $team,
            'team.transfer_requested',
            'organization.notifications.team_transfer_requested_title',
            'organization.notifications.team_transfer_requested_body',
            ['user' => $request->user()->name, 'source' => $sourceTeam->name, 'target' => $team->name],
            [
                'url' => '/teams/'.$team->id,
                'club_id' => $team->club_id,
                'source_team_id' => $sourceTeam->id,
                'target_team_id' => $team->id,
                'team_transfer_request_id' => $transferRequest->id,
                'effective_on' => $transferRequest->effective_on?->toDateString(),
            ],
            $request->user()->id,
        );

        return response()->json(['data' => $this->teamTransferPayload($transferRequest->fresh(['sourceTeam', 'targetTeam', 'user']))], 201);
    }

    public function storeTransfer(Request $request, Team $team)
    {
        $this->authorize('manageMembers', $team);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'source_team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')->where('club_id', $team->club_id)],
            'role' => ['required', Rule::in(Team::ROLES)],
            'effective_on' => ['nullable', 'date'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $member = User::findOrFail($data['user_id']);
        abort_unless($team->club->users()->where('users.id', $member->id)->exists(), 422, __('organization.team.club_member_required'));

        $sourceTeam = filled($data['source_team_id'] ?? null)
            ? Team::query()->where('club_id', $team->club_id)->findOrFail($data['source_team_id'])
            : $this->currentSourceTeamForTransfer($team, $member);

        abort_if($sourceTeam?->is($team), 422, __('organization.team.transfer_same_team'));
        abort_if($team->users()->where('users.id', $member->id)->exists(), 422, __('organization.team.already_member'));

        $transferRequest = TeamTransferRequest::query()->create([
            'club_id' => $team->club_id,
            'user_id' => $member->id,
            'source_team_id' => $sourceTeam?->id,
            'target_team_id' => $team->id,
            'role' => $data['role'],
            'status' => 'approved',
            'origin' => 'administrative',
            'effective_on' => $data['effective_on'] ?? now()->toDateString(),
            'message' => $data['message'] ?? null,
            'requested_by' => $request->user()->id,
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
        ]);

        $this->applyTeamTransfer($transferRequest, $request->user(), 'club.team_transfer.administrative');

        return response()->json(['data' => $this->teamTransferPayload($transferRequest->fresh(['sourceTeam', 'targetTeam', 'user', 'decider']))], 201);
    }

    public function approveTransferRequest(Request $request, TeamTransferRequest $transferRequest)
    {
        $transferRequest->loadMissing(['targetTeam.club', 'sourceTeam', 'user']);
        $this->authorize('manageMembers', $transferRequest->targetTeam);

        abort_if((int) $transferRequest->requested_by === (int) $request->user()->id, 422, __('organization.team.transfer_second_person'));
        abort_unless($transferRequest->status === 'pending', 422, __('organization.team.transfer_request_closed'));

        $transferRequest->update([
            'status' => 'approved',
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
        ]);

        ClubAuditLog::record($transferRequest->club, $request->user(), 'club.team_transfer.approved', $transferRequest, $this->transferAuditData($transferRequest));
        $this->applyTeamTransfer($transferRequest->fresh(['targetTeam.club', 'sourceTeam', 'user']), $request->user());

        return response()->json(['data' => $this->teamTransferPayload($transferRequest->fresh(['sourceTeam', 'targetTeam', 'user', 'decider']))]);
    }

    public function declineTransferRequest(Request $request, TeamTransferRequest $transferRequest)
    {
        $transferRequest->loadMissing(['targetTeam.club', 'sourceTeam', 'user']);
        $this->authorize('manageMembers', $transferRequest->targetTeam);
        abort_unless($transferRequest->status === 'pending', 422, __('organization.team.transfer_request_closed'));

        $transferRequest->update([
            'status' => 'declined',
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
        ]);

        ClubAuditLog::record($transferRequest->club, $request->user(), 'club.team_transfer.declined', $transferRequest, $this->transferAuditData($transferRequest));
        AppNotification::sendLocalized(
            $transferRequest->user_id,
            'team.transfer_declined',
            'organization.notifications.team_transfer_declined_title',
            'organization.notifications.team_transfer_declined_body',
            ['target' => $transferRequest->targetTeam?->name ?? __('organization.notifications.team_transfer_target_fallback')],
            $this->transferNotificationData($transferRequest),
        );

        return response()->json(['data' => $this->teamTransferPayload($transferRequest->fresh(['sourceTeam', 'targetTeam', 'user', 'decider']))]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'club_id' => ['required', 'exists:clubs,id'],
            'name' => ['required', 'string', 'max:255'],
            'sport_type' => ['nullable', 'string', 'max:120'],
            'club_department_id' => ['nullable', Rule::exists('club_departments', 'id')->where('club_id', $request->integer('club_id'))],
            'club_location_id' => ['nullable', Rule::exists('club_locations', 'id')->where('club_id', $request->integer('club_id'))],
            'club_training_group_id' => ['nullable', Rule::exists('club_training_groups', 'id')->where('club_id', $request->integer('club_id'))],
            'sport_year_period_id' => ['sometimes', 'nullable', 'integer', Rule::exists('club_year_periods', 'id')->where(fn ($query) => $query
                ->where('club_id', $request->integer('club_id'))
                ->where('type', 'sport'))],
            ...$this->planningRules(),
        ]);

        $club = Club::findOrFail($data['club_id']);
        $data = $this->normalizeOrganizationAssignment($data, $club);
        $existingTeam = Team::query()
            ->with('club')
            ->where('club_id', $club->id)
            ->where('name', $data['name'])
            ->first();
        $this->authorizeTeamStoreTarget($club, $request->user(), $data, $existingTeam);

        if (! $existingTeam) {
            $this->planFeatures->ensureCanCreateTeam($club);
        }

        $team = DB::transaction(function () use ($club, $data, $request, $existingTeam) {
            $team = $existingTeam ?: Team::query()->create([
                'club_id' => $club->id,
                'name' => $data['name'],
                'sport_type' => $data['sport_type'] ?? $club->sport_type ?? 'fussball',
                'club_department_id' => $data['club_department_id'] ?? null,
                'club_location_id' => $data['club_location_id'] ?? null,
                'club_training_group_id' => $data['club_training_group_id'] ?? null,
                'sport_year_period_id' => $data['sport_year_period_id'] ?? null,
                ...$this->planningPayload($data),
            ]);

            if ($existingTeam) {
                $team->update([
                    'sport_type' => $data['sport_type'] ?? $team->sport_type,
                    'club_department_id' => $data['club_department_id'] ?? $team->club_department_id,
                    'club_location_id' => $data['club_location_id'] ?? $team->club_location_id,
                    'club_training_group_id' => $data['club_training_group_id'] ?? $team->club_training_group_id,
                    'sport_year_period_id' => array_key_exists('sport_year_period_id', $data)
                        ? $data['sport_year_period_id']
                        : $team->sport_year_period_id,
                    ...$this->planningPayload($data, $team),
                ]);
            }

            $team->users()->syncWithoutDetaching([
                $request->user()->id => ['role' => TeamRoles::COACH],
            ]);

            return $team;
        });

        return (new TeamResource($team->fresh()->load(['club', 'users', 'sportYearPeriod'])->loadCount(['users', 'events'])))
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
            'club_department_id' => ['nullable', Rule::exists('club_departments', 'id')->where('club_id', $team->club_id)],
            'club_location_id' => ['nullable', Rule::exists('club_locations', 'id')->where('club_id', $team->club_id)],
            'club_training_group_id' => ['nullable', Rule::exists('club_training_groups', 'id')->where('club_id', $team->club_id)],
            'sport_year_period_id' => ['sometimes', 'nullable', 'integer', Rule::exists('club_year_periods', 'id')->where(fn ($query) => $query
                ->where('club_id', $team->club_id)
                ->where('type', 'sport'))],
            ...$this->planningRules(),
        ]);
        foreach (['club_department_id', 'club_location_id', 'club_training_group_id'] as $field) {
            if (! array_key_exists($field, $data)) {
                $data[$field] = $team->getAttribute($field);
            }
        }
        $data = $this->normalizeOrganizationAssignment($data, $team->club);
        $nextDepartmentId = filled($data['club_department_id'] ?? null)
            ? (int) $data['club_department_id']
            : null;
        $currentDepartmentId = $team->club_department_id ? (int) $team->club_department_id : null;
        if ($nextDepartmentId !== $currentDepartmentId) {
            abort_unless(
                ClubPermissions::allowsTeamDepartmentChange($team, $request->user(), $nextDepartmentId),
                403,
            );
        }

        $team->update([
            'name' => $data['name'],
            'sport_type' => $data['sport_type'] ?? $team->sport_type,
            'club_department_id' => $data['club_department_id'] ?? null,
            'club_location_id' => $data['club_location_id'] ?? null,
            'club_training_group_id' => $data['club_training_group_id'] ?? null,
            'sport_year_period_id' => array_key_exists('sport_year_period_id', $data)
                ? $data['sport_year_period_id']
                : $team->sport_year_period_id,
            ...$this->planningPayload($data, $team),
        ]);

        if ($team->wasChanged(['name', 'sport_type'])) {
            $this->notifyTeamProfileUpdated($team->fresh(['users', 'club']), $request->user(), $previousName, $previousSportType);
        }

        if ($team->wasChanged(['club_department_id', 'club_location_id', 'club_training_group_id'])) {
            ClubAuditLog::record($team->club, $request->user(), 'club.organization.team_assigned', $team, [
                'team_id' => $team->id,
                'changed_fields' => array_values(array_intersect(
                    ['club_department_id', 'club_location_id', 'club_training_group_id'],
                    array_keys($team->getChanges()),
                )),
            ]);
        }

        if ($team->wasChanged('sport_year_period_id')) {
            ClubAuditLog::record($team->club, $request->user(), 'club.sport_year.team_assigned', $team, [
                'team_id' => $team->id,
                'sport_year_period_id' => $team->sport_year_period_id,
            ]);
        }

        return new TeamResource($team->fresh()->load(['club', 'users', 'sportYearPeriod'])->loadCount(['users', 'events']));
    }

    public function destroy(Request $request, Team $team)
    {
        $this->authorize('delete', $team);

        if ($team->inventoryItems()->exists()) {
            throw ValidationException::withMessages(['team' => __('validation.organization_unit_in_use')]);
        }

        $team->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function approveJoinRequest(Request $request, Team $team, TeamJoinRequest $joinRequest)
    {
        abort_unless($joinRequest->team_id === $team->id, 404);
        $this->authorize('manageMembers', $team);

        if ($joinRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'join_request' => __('organization.team.join_request_closed'),
            ]);
        }
        abort_if(
            (int) $joinRequest->user_id === (int) $request->user()->id,
            422,
            __('organization.team.join_request_second_person'),
        );

        $data = $request->validate([
            'role' => ['nullable', Rule::in(Team::ROLES)],
        ]);

        abort_if(
            ! $team->club->users()->where('users.id', $joinRequest->user_id)->exists()
            && ! $team->club->canAddMembers(),
            422,
            __('organization.team.club_member_limit_reached')
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

        AppNotification::sendLocalized(
            $joinRequest->user_id,
            'team.join_request_accepted',
            'organization.notifications.team_join_accepted_title',
            'organization.notifications.team_join_accepted_body',
            ['team' => $team->name],
            [
                'team_id' => $team->id,
                'club_id' => $team->club_id,
            ],
        );

        return new TeamResource($team->fresh()->load(['club.users', 'users', 'joinRequests.user'])->loadCount(['users', 'events']));
    }

    public function approveJoinRequestById(Request $request, TeamJoinRequest $joinRequest)
    {
        return $this->approveJoinRequest($request, $joinRequest->team, $joinRequest);
    }

    public function declineJoinRequest(Request $request, Team $team, TeamJoinRequest $joinRequest)
    {
        abort_unless($joinRequest->team_id === $team->id, 404);
        $this->authorize('manageMembers', $team);

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
            ['team' => $team->name],
            [
                'team_id' => $team->id,
                'club_id' => $team->club_id,
            ],
        );

        return new TeamResource($team->fresh()->load(['club.users', 'users', 'joinRequests.user'])->loadCount(['users', 'events']));
    }

    public function declineJoinRequestById(Request $request, TeamJoinRequest $joinRequest)
    {
        return $this->declineJoinRequest($request, $joinRequest->team, $joinRequest);
    }

    public function invite(Request $request, Team $team)
    {
        $this->authorize('manageMembers', $team);

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
                    'email' => __('organization.team.email_invitation_failed'),
                ]);
            }

            return response()->json([
                'data' => $this->teamInvitationPayload($invitation->fresh(['team.club:id,name', 'inviter:id,name,email'])),
                'message' => __('organization.team.email_invitation_sent'),
            ], 201);
        }

        if ($team->users()->where('users.id', $recipient->id)->exists()) {
            throw ValidationException::withMessages([
                'email' => __('organization.team.already_member'),
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
            ],
        );

        return response()->json([
            'data' => $this->teamInvitationPayload($invitation->fresh(['team.club:id,name', 'inviter:id,name,email'])),
            'message' => __('organization.team.invitation_sent'),
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
            __('organization.team.club_member_limit_reached')
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
            __('organization.team.club_member_limit_reached')
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
        $this->authorize('updateMemberRole', $team);
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
                        'organization.roles.team.'.strtolower($data['role']),
                        $newRoleLabel,
                    ),
                ],
                [
                    'url' => '/notifications',
                    'mobile_url' => 'airmius://teams/'.$team->id,
                    'team_id' => $team->id,
                    'club_id' => $team->club_id,
                    'previous_role' => $previousRole,
                    'role' => $data['role'],
                ],
            );
        }

        return new TeamResource($team->fresh()->load(['club.users', 'users', 'joinRequests.user'])->loadCount(['users', 'events']));
    }

    public function storeMember(Request $request, Team $team)
    {
        $this->authorize('manageMembers', $team);

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
                'url' => '/teams/'.$team->id,
                'club_id' => $team->club_id,
                'team_id' => $team->id,
                'role' => $data['role'],
                'added_by' => $request->user()->id,
            ],
        );

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
            $this->authorize('removeMember', $team);
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
            $this->notifyTeamManagersLocalized(
                $team,
                'team.member_left',
                'organization.notifications.team_member_left_title',
                'organization.notifications.team_member_left_body',
                [
                    'user' => $user->name,
                    'team' => $team->name,
                    'reason' => filled($data['reason'] ?? null) ? "\n\n".$data['reason'] : '',
                ],
                [
                    'url' => '/teams/'.$team->id,
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
                    'url' => '/notifications',
                    'club_id' => $team->club_id,
                    'team_id' => $team->id,
                ],
            );
        }

        return new TeamResource($team->fresh()->load(['club.users', 'users', 'joinRequests.user'])->loadCount(['users', 'events']));
    }

    private function canRemoveTeamMembers(User $user, Team $team): bool
    {
        return $user->can('removeMember', $team);
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

    private function notifyTeamManagersLocalized(
        Team $team,
        string $type,
        string $titleKey,
        ?string $bodyKey,
        array $replace,
        array $data,
        ?int $exceptUserId = null,
    ): void {
        $team->club->users()
            ->get(['users.id', 'users.language'])
            ->concat($team->users()->get(['users.id', 'users.language']))
            ->unique('id')
            ->when($exceptUserId, fn ($users) => $users->where('id', '!=', $exceptUserId))
            ->filter(fn (User $manager) => $manager->can('manageMembers', $team))
            ->each(fn (User $manager) => AppNotification::sendLocalized(
                $manager,
                $type,
                $titleKey,
                $bodyKey,
                $replace,
                $data,
            ));
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
        $team->users
            ->where('id', '!=', $actor->id)
            ->each(fn (User $member) => AppNotification::sendLocalized(
                $member,
                'team.profile_updated',
                'organization.notifications.team_profile_updated_title',
                'organization.notifications.team_profile_updated_body',
                ['user' => $actor->name, 'team' => $team->name, 'changes' => ''],
                [
                    'url' => '/teams/'.$team->id,
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

    private function perPage(Request $request): int
    {
        return min(max((int) $request->integer('per_page', 20), 1), 50);
    }

    private function normalizeOrganizationAssignment(array $data, Club $club): array
    {
        if (empty($data['club_training_group_id'])) {
            return $data;
        }

        $group = ClubTrainingGroup::query()
            ->where('club_id', $club->id)
            ->findOrFail($data['club_training_group_id']);

        foreach ([
            'club_department_id' => $group->club_department_id,
            'club_location_id' => $group->club_location_id,
        ] as $field => $groupValue) {
            if (! empty($data[$field]) && $groupValue && (int) $data[$field] !== (int) $groupValue) {
                throw ValidationException::withMessages([
                    $field => __('validation.organization_assignment_mismatch'),
                ]);
            }

            if (empty($data[$field]) && $groupValue) {
                $data[$field] = $groupValue;
            }
        }

        return $data;
    }

    private function planningRules(): array
    {
        return [
            'birth_year_from' => ['sometimes', 'nullable', 'integer', 'min:1900', 'max:2100'],
            'birth_year_to' => ['sometimes', 'nullable', 'integer', 'min:1900', 'max:2100', 'gte:birth_year_from'],
            'performance_level' => ['sometimes', 'nullable', 'string', 'max:120'],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000'],
            'waitlist_enabled' => ['sometimes', 'boolean'],
            'valid_from' => ['sometimes', 'nullable', 'date'],
            'valid_until' => ['sometimes', 'nullable', 'date', 'after_or_equal:valid_from'],
        ];
    }

    private function planningPayload(array $data, ?Team $team = null): array
    {
        $payload = [];

        foreach (['birth_year_from', 'birth_year_to', 'performance_level', 'capacity', 'valid_from', 'valid_until'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            } elseif ($team) {
                $payload[$field] = $team->getAttribute($field);
            }
        }

        if (array_key_exists('waitlist_enabled', $data)) {
            $payload['waitlist_enabled'] = (bool) $data['waitlist_enabled'];
        } elseif ($team) {
            $payload['waitlist_enabled'] = (bool) $team->waitlist_enabled;
        }

        return $payload;
    }

    private function authorizeTeamStoreTarget(Club $club, User $user, array $data, ?Team $existingTeam): void
    {
        $canEditGlobally = ClubPermissions::allows($club, $user, ClubPermissions::TEAMS_EDIT);
        $departmentId = filled($data['club_department_id'] ?? null)
            ? (int) $data['club_department_id']
            : null;

        abort_unless(
            $canEditGlobally || ($departmentId && ClubPermissions::allowsInScope(
                $club,
                $user,
                ClubPermissions::TEAMS_EDIT,
                'department',
                $departmentId,
            )),
            403,
        );

        if ($existingTeam) {
            abort_unless(
                $canEditGlobally || ClubPermissions::allowsForTeam(
                    $existingTeam,
                    $user,
                    ClubPermissions::TEAMS_EDIT,
                ),
                403,
            );
        }
    }

    private function applyTeamTransfer(TeamTransferRequest $transferRequest, User $actor, string $auditType = 'club.team_transfer.applied'): void
    {
        $transferRequest->loadMissing(['club', 'sourceTeam', 'targetTeam', 'user']);

        DB::transaction(function () use ($transferRequest, $actor, $auditType) {
            if ($transferRequest->sourceTeam) {
                $transferRequest->sourceTeam->users()->detach($transferRequest->user_id);
                $this->removeTeamChatMember($transferRequest->sourceTeam, $transferRequest->user_id);
            }

            $transferRequest->targetTeam->users()->syncWithoutDetaching([
                $transferRequest->user_id => ['role' => $transferRequest->role],
            ]);
            $this->syncTeamChatMembers($transferRequest->targetTeam, [$transferRequest->user_id]);

            $transferRequest->update([
                'status' => 'applied',
                'applied_at' => now(),
                'decided_by' => $transferRequest->decided_by ?: $actor->id,
                'decided_at' => $transferRequest->decided_at ?: now(),
            ]);

            DB::table('club_member_timeline_entries')->insert([
                'club_id' => $transferRequest->club_id,
                'subject_type' => 'member',
                'subject_id' => $transferRequest->user_id,
                'type' => 'team_transfer',
                'title' => __('organization.team.transfer_timeline_title'),
                'description' => __('organization.team.transfer_timeline_description', [
                    'source' => $transferRequest->sourceTeam?->name ?? __('organization.team.transfer_source_none'),
                    'target' => $transferRequest->targetTeam->name,
                ]),
                'occurred_on' => $transferRequest->effective_on,
                'from_value' => $transferRequest->sourceTeam?->name,
                'to_value' => $transferRequest->targetTeam->name,
                'created_by' => $actor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            ClubAuditLog::record($transferRequest->club, $actor, $auditType, $transferRequest, $this->transferAuditData($transferRequest));
        });

        AppNotification::sendLocalized(
            $transferRequest->user_id,
            'team.transfer_applied',
            'organization.notifications.team_transfer_applied_title',
            'organization.notifications.team_transfer_applied_body',
            [
                'source' => $transferRequest->sourceTeam?->name ?? __('organization.notifications.team_transfer_source_none'),
                'target' => $transferRequest->targetTeam->name,
            ],
            $this->transferNotificationData($transferRequest),
        );
    }

    private function assertNoOpenTransfer(Team $targetTeam, int $userId): void
    {
        $exists = TeamTransferRequest::query()
            ->where('club_id', $targetTeam->club_id)
            ->where('user_id', $userId)
            ->where('target_team_id', $targetTeam->id)
            ->where('status', 'pending')
            ->exists();

        abort_if($exists, 422, __('organization.team.transfer_request_pending'));
    }

    private function currentSourceTeamForTransfer(Team $targetTeam, User $member): ?Team
    {
        return $member->teams()
            ->where('teams.club_id', $targetTeam->club_id)
            ->whereKeyNot($targetTeam->id)
            ->oldest('teams.id')
            ->first();
    }

    private function transferAuditData(TeamTransferRequest $transferRequest): array
    {
        return [
            'team_transfer_request_id' => $transferRequest->id,
            'club_id' => $transferRequest->club_id,
            'team_id' => $transferRequest->target_team_id,
            'user_id' => $transferRequest->user_id,
            'source_team_id' => $transferRequest->source_team_id,
            'target_team_id' => $transferRequest->target_team_id,
            'status' => $transferRequest->status,
            'origin' => $transferRequest->origin,
            'effective_on' => $transferRequest->effective_on?->toDateString(),
            'requested_by' => $transferRequest->requested_by,
            'decided_by' => $transferRequest->decided_by,
        ];
    }

    private function transferNotificationData(TeamTransferRequest $transferRequest): array
    {
        return [
            'url' => '/teams/'.$transferRequest->target_team_id,
            'club_id' => $transferRequest->club_id,
            'source_team_id' => $transferRequest->source_team_id,
            'target_team_id' => $transferRequest->target_team_id,
            'team_transfer_request_id' => $transferRequest->id,
            'effective_on' => $transferRequest->effective_on?->toDateString(),
            'origin' => $transferRequest->origin,
        ];
    }

    private function teamTransferPayload(TeamTransferRequest $transferRequest): array
    {
        return [
            'id' => $transferRequest->id,
            'club_id' => $transferRequest->club_id,
            'user_id' => $transferRequest->user_id,
            'source_team_id' => $transferRequest->source_team_id,
            'target_team_id' => $transferRequest->target_team_id,
            'role' => $transferRequest->role,
            'status' => $transferRequest->status,
            'origin' => $transferRequest->origin,
            'effective_on' => $transferRequest->effective_on?->toDateString(),
            'requested_by' => $transferRequest->requested_by,
            'decided_by' => $transferRequest->decided_by,
            'decided_at' => $transferRequest->decided_at?->toJSON(),
            'applied_at' => $transferRequest->applied_at?->toJSON(),
            'source_team' => $transferRequest->sourceTeam ? [
                'id' => $transferRequest->sourceTeam->id,
                'name' => $transferRequest->sourceTeam->name,
            ] : null,
            'target_team' => $transferRequest->targetTeam ? [
                'id' => $transferRequest->targetTeam->id,
                'name' => $transferRequest->targetTeam->name,
            ] : null,
            'user' => $transferRequest->user ? [
                'id' => $transferRequest->user->id,
                'name' => $transferRequest->user->name,
                'email' => $transferRequest->user->email,
            ] : null,
        ];
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

        if (ClubPermissions::allowsForTeam($team, $user, ClubPermissions::MEMBERS_VIEW)) {
            return true;
        }

        return $team->users()
            ->where('users.id', $user->id)
            ->wherePivot('role', TeamRoles::COACH)
            ->exists();
    }
}
