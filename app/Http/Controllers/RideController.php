<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Ride;
use App\Models\Team;
use App\Models\User;
use App\Services\RideService;
use App\Support\AppNotification;
use App\Support\Roles;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class RideController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private RideService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Ride::class);
        $user = $request->user();
        $friendIds = $user->friendships()->pluck('friend_id');
        $clubIds = $this->relatedClubIds($user);
        $teamIds = $this->relatedTeamIds($user);
        $canManageAllRides = $this->canManageAllRides($user);

        $rides = Ride::query()
            ->with(['driver:id,name', 'users:id,name', 'club:id,name', 'team:id,name'])
            ->when(! $canManageAllRides, function ($query) use ($user, $friendIds, $clubIds, $teamIds) {
                $query
                    ->where(function ($query) use ($user, $friendIds, $clubIds, $teamIds) {
                        $query
                            ->where('driver_id', $user->id)
                            ->orWhereHas('users', fn ($users) => $users->where('users.id', $user->id))
                            ->orWhere('visibility', 'public')
                            ->orWhere(fn ($query) => $query
                                ->where('visibility', 'friends')
                                ->whereIn('driver_id', $friendIds))
                            ->orWhere(fn ($query) => $query
                                ->where('visibility', 'club')
                                ->whereIn('club_id', $clubIds))
                            ->orWhere(fn ($query) => $query
                                ->where('visibility', 'team')
                                ->whereIn('team_id', $teamIds));
                    });
            })
            ->orderBy('departure_time')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Ride $ride) use ($user) {
                $isDriver = (int) $ride->driver_id === (int) $user->id;
                $acceptedUsers = $ride->users->filter(fn (User $member) => $member->pivot?->status === Ride::MEMBER_STATUS_ACCEPTED)->values();
                $pendingUsers = $ride->users->filter(fn (User $member) => $member->pivot?->status === Ride::MEMBER_STATUS_REQUESTED)->values();
                $ownPivot = $ride->users->firstWhere('id', $user->id)?->pivot;
                $isJoined = $ownPivot?->status === Ride::MEMBER_STATUS_ACCEPTED;
                $hasPendingRequest = $ownPivot?->status === Ride::MEMBER_STATUS_REQUESTED;
                $canUpdate = $user->can('update', $ride);
                $joinState = $this->resolveJoinState(
                    $ride,
                    $user,
                    $acceptedUsers->count(),
                    $isDriver,
                    $isJoined,
                    $hasPendingRequest,
                );
                $canSeePrivateDetails = $isDriver || $isJoined || $canUpdate;

                return [
                    'id' => $ride->id,
                    'club_id' => $ride->club_id,
                    'team_id' => $ride->team_id,
                    'club' => $ride->club,
                    'team' => $ride->team,
                    'driver_id' => $ride->driver_id,
                    'driver' => $ride->driver,
                    'visibility' => $ride->visibility,
                    'from' => $ride->from,
                    'to' => $ride->to,
                    'pickup_name' => $ride->pickup_name,
                    'pickup_street' => $canSeePrivateDetails ? $ride->pickup_street : null,
                    'pickup_house_number' => $canSeePrivateDetails ? $ride->pickup_house_number : null,
                    'pickup_postal_code' => $ride->pickup_postal_code,
                    'pickup_city' => $ride->pickup_city,
                    'pickup_country' => $ride->pickup_country,
                    'pickup_note' => $canSeePrivateDetails ? $ride->pickup_note : null,
                    'pickup_public_label' => $this->pickupPublicLabel($ride),
                    'pickup_private_label' => $canSeePrivateDetails ? $this->pickupPrivateLabel($ride) : null,
                    'departure_time' => $ride->departure_time,
                    'seats' => $ride->seats,
                    'contact_details' => $canSeePrivateDetails ? $ride->contact_details : null,
                    'users' => $canSeePrivateDetails ? $acceptedUsers->map(fn (User $member) => [
                        'id' => $member->id,
                        'name' => $member->name,
                        'can_remove' => $canUpdate && (int) $member->id !== (int) $ride->driver_id,
                    ])->values() : [],
                    'participants_count' => $acceptedUsers->count(),
                    'pending_requests' => $isDriver ? $pendingUsers->map(fn (User $member) => [
                        'id' => $member->id,
                        'name' => $member->name,
                        'message' => $member->pivot?->message,
                        'requested_at' => $member->pivot?->created_at,
                    ])->values() : [],
                    'is_driver' => $isDriver,
                    'is_joined' => $isJoined,
                    'has_pending_request' => $hasPendingRequest,
                    'can_join' => $joinState['can_join'],
                    'join_block_reason' => $joinState['join_block_reason'],
                    'can_update' => $canUpdate,
                    'can_delete' => $user->can('delete', $ride),
                ];
            });

        return Inertia::render('Auth/Dashboard/Rides/Index', [
            'rides' => $rides,
            'clubs' => Club::query()
                ->whereIn('id', $clubIds)
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get(),
            'teams' => Team::query()
                ->whereIn('id', $teamIds)
                ->select(['id', 'name', 'club_id'])
                ->orderBy('name')
                ->get(),
            'visibilities' => [
                ['value' => 'friends', 'label' => __('rides.visibility.friends.label'), 'description' => __('rides.visibility.friends.description')],
                ['value' => 'club', 'label' => __('rides.visibility.club.label'), 'description' => __('rides.visibility.club.description')],
                ['value' => 'team', 'label' => __('rides.visibility.team.label'), 'description' => __('rides.visibility.team.description')],
                ['value' => 'public', 'label' => __('rides.visibility.public.label'), 'description' => __('rides.visibility.public.description')],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Ride::class);

        $data = $this->validatedRideData($request);

        $ride = $this->service->create($request->user(), $data);
        $this->service->join($ride, $request->user());

        return back()->with('success', __('rides.flash.created'));
    }

    public function update(Request $request, Ride $ride)
    {
        $this->authorize('update', $ride);

        $data = $this->validatedRideData($request);
        $participantsCount = $ride->acceptedUsers()->count();

        abort_if((int) $data['seats'] < $participantsCount, 422, __('rides.flash.seats_below_participants'));

        $ride->update([
            ...$data,
            'club_id' => $data['visibility'] === 'club' ? ($data['club_id'] ?? null) : ($data['visibility'] === 'team' ? ($data['club_id'] ?? null) : null),
            'team_id' => $data['visibility'] === 'team' ? ($data['team_id'] ?? null) : null,
        ]);

        $this->notifyParticipants(
            $ride->fresh(['users', 'driver']),
            'ride.updated',
            'rides.notifications.updated_title',
            'rides.notifications.updated_body',
            ['from' => $ride->from, 'to' => $ride->to],
            [
                'ride_id' => $ride->id,
                'url' => route('auth.rides.index'),
            ],
            excludeUserId: $request->user()->id,
        );

        return back()->with('success', __('rides.flash.updated'));
    }

    public function join(Ride $ride)
    {
        $ride->loadMissing('users:id,name');
        $user = auth()->user();
        $ownPivot = $ride->users->firstWhere('id', $user->id)?->pivot;
        $isJoined = $ownPivot?->status === Ride::MEMBER_STATUS_ACCEPTED;
        $hasPendingRequest = $ownPivot?->status === Ride::MEMBER_STATUS_REQUESTED;
        $acceptedCount = $ride->users->filter(fn (User $member) => $member->pivot?->status === Ride::MEMBER_STATUS_ACCEPTED)->count();

        $joinState = $this->resolveJoinState(
            $ride,
            $user,
            $acceptedCount,
            (int) $ride->driver_id === (int) $user->id,
            $isJoined,
            $hasPendingRequest,
        );

        if (! $joinState['can_join']) {
            return back()->with('error', $this->joinBlockMessage($joinState['join_block_reason']));
        }

        $data = request()->validate([
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $this->service->requestToJoin($ride, auth()->user(), $data['message'] ?? null);

        if ($result === RideService::RESULT_FULL) {
            return back()->with('error', __('rides.flash.full'));
        }

        if ($result === RideService::RESULT_ALREADY_JOINED) {
            return back()->with('success', __('rides.flash.already_joined'));
        }

        if ($result === RideService::RESULT_ALREADY_REQUESTED) {
            return back()->with('success', __('rides.flash.already_requested'));
        }

        if ($result === RideService::RESULT_REQUESTED && (int) $ride->driver_id !== (int) auth()->id()) {
            AppNotification::sendLocalized(
                $ride->driver_id,
                'ride.requested',
                'rides.notifications.requested_title',
                'rides.notifications.requested_body',
                ['name' => auth()->user()->name, 'from' => $ride->from, 'to' => $ride->to],
                [
                    'ride_id' => $ride->id,
                    'url' => route('auth.rides.index'),
                ],
            );
        }

        if ($result !== RideService::RESULT_REQUESTED
            && $result !== RideService::RESULT_ALREADY_JOINED
            && $result !== RideService::RESULT_ALREADY_REQUESTED
        ) {
            return back()->with('error', __('rides.flash.request_failed'));
        }

        return back()->with('success', __('rides.flash.request_sent'));
    }

    public function leave(Ride $ride)
    {
        $user = auth()->user();
        $ownPivot = $ride->users()
            ->where('users.id', $user->id)
            ->first()?->pivot;

        if ((int) $ride->driver_id === (int) $user->id) {
            return back()->with('error', __('rides.flash.driver_cannot_leave'));
        }

        if (! $ownPivot) {
            return back()->with('error', __('rides.flash.not_participant'));
        }

        if ($ownPivot->status === Ride::MEMBER_STATUS_REQUESTED) {
            $ride->users()->detach($user->id);

            return back()->with('success', __('rides.flash.request_withdrawn'));
        }

        if ($ownPivot->status !== Ride::MEMBER_STATUS_ACCEPTED) {
            return back()->with('error', __('rides.flash.action_unavailable'));
        }

        $ride->users()->detach($user->id);
        AppNotification::sendLocalized(
            $ride->driver_id,
            'ride.left',
            'rides.notifications.left_title',
            'rides.notifications.left_body',
            ['name' => $user->name, 'from' => $ride->from, 'to' => $ride->to],
            [
                'ride_id' => $ride->id,
                'url' => route('auth.rides.index'),
            ],
        );

        return back()->with('success', __('rides.flash.left'));
    }

    public function approveRequest(Ride $ride, User $user)
    {
        $this->authorize('update', $ride);
        $result = $this->service->approveRequest($ride, $user);

        if ($result === RideService::RESULT_FULL) {
            return back()->with('error', __('rides.flash.full'));
        }

        if ($result !== RideService::RESULT_APPROVED) {
            return back()->with('error', __('rides.flash.request_not_found'));
        }

        AppNotification::sendLocalized(
            $user,
            'ride.request_approved',
            'rides.notifications.approved_title',
            'rides.notifications.approved_body',
            ['from' => $ride->from, 'to' => $ride->to],
            [
                'ride_id' => $ride->id,
                'url' => route('auth.rides.index'),
            ],
        );

        return back()->with('success', __('rides.flash.request_approved'));
    }

    public function rejectRequest(Ride $ride, User $user)
    {
        $this->authorize('update', $ride);
        $updated = $this->service->rejectRequest($ride, $user);

        if (! $updated) {
            return back()->with('error', __('rides.flash.request_not_found'));
        }

        AppNotification::sendLocalized(
            $user,
            'ride.request_rejected',
            'rides.notifications.rejected_title',
            'rides.notifications.rejected_body',
            ['from' => $ride->from, 'to' => $ride->to],
            [
                'ride_id' => $ride->id,
                'url' => route('auth.rides.index'),
            ],
        );

        return back()->with('success', __('rides.flash.request_rejected'));
    }

    public function removeMember(Ride $ride, User $user)
    {
        $this->authorize('update', $ride);
        $memberPivot = $ride->users()
            ->where('users.id', $user->id)
            ->first()?->pivot;

        if (! $memberPivot) {
            return back()->with('error', __('rides.flash.active_member_not_found'));
        }

        if ((int) $user->id === (int) $ride->driver_id) {
            return back()->with('error', __('rides.flash.driver_cannot_be_removed'));
        }

        if ($memberPivot->status !== Ride::MEMBER_STATUS_ACCEPTED) {
            return back()->with('error', __('rides.flash.only_active_member'));
        }

        $ride->users()->detach($user->id);

        AppNotification::sendLocalized(
            $user,
            'ride.member_removed',
            'rides.notifications.removed_title',
            'rides.notifications.removed_body',
            ['from' => $ride->from, 'to' => $ride->to],
            [
                'ride_id' => $ride->id,
                'url' => route('auth.rides.index'),
            ],
        );

        return back()->with('success', __('rides.flash.member_removed'));
    }

    public function destroy(Ride $ride)
    {
        $this->authorize('delete', $ride);
        $participants = $ride->users()->where('users.id', '!=', $ride->driver_id)->wherePivotIn('status', [Ride::MEMBER_STATUS_REQUESTED, Ride::MEMBER_STATUS_ACCEPTED])->get();
        foreach ($participants as $participant) {
            AppNotification::sendLocalized(
                $participant,
                'ride.deleted',
                'rides.notifications.deleted_title',
                'rides.notifications.deleted_body',
                ['from' => $ride->from, 'to' => $ride->to],
                [
                    'ride_id' => $ride->id,
                    'url' => route('auth.rides.index'),
                ],
            );
        }

        $ride->delete();

        return back()->with('success', __('rides.flash.deleted'));
    }

    private function validatedRideData(Request $request): array
    {
        $data = $request->validate([
            'visibility' => ['required', Rule::in(Ride::VISIBILITIES)],
            'club_id' => ['nullable', 'integer', Rule::exists('clubs', 'id')],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')],
            'from' => ['required', 'string', 'max:255', 'different:to'],
            'to' => ['required', 'string', 'max:255', 'different:from'],
            'pickup_name' => ['nullable', 'string', 'max:255'],
            'pickup_street' => ['nullable', 'string', 'max:255'],
            'pickup_house_number' => ['nullable', 'string', 'max:40'],
            'pickup_postal_code' => ['nullable', 'string', 'max:30'],
            'pickup_city' => ['nullable', 'string', 'max:255'],
            'pickup_country' => ['nullable', 'string', 'size:2'],
            'pickup_note' => ['nullable', 'string', 'max:500'],
            'departure_time' => ['required', 'date', 'after_or_equal:'.now()->addMinutes(10)->toDateTimeString()],
            'seats' => ['required', 'integer', 'min:1', 'max:20'],
            'contact_details' => ['nullable', 'string', 'max:1000'],
        ]);

        $request->validate([
            'club_id' => [$data['visibility'] === 'club' ? 'required' : 'nullable'],
            'team_id' => [$data['visibility'] === 'team' ? 'required' : 'nullable'],
        ]);

        $clubIds = $this->relatedClubIds($request->user());
        $teamIds = $this->relatedTeamIds($request->user());

        if (! empty($data['club_id']) && ! $clubIds->contains((int) $data['club_id'])) {
            abort(403);
        }

        if (! empty($data['team_id']) && ! $teamIds->contains((int) $data['team_id'])) {
            abort(403);
        }

        if (! empty($data['team_id']) && empty($data['club_id'])) {
            $data['club_id'] = Team::query()->whereKey($data['team_id'])->value('club_id');
        }

        if (isset($data['pickup_country'])) {
            $data['pickup_country'] = strtoupper((string) $data['pickup_country']);
        }

        return $data;
    }

    private function pickupPublicLabel(Ride $ride): ?string
    {
        $parts = array_filter([
            $ride->pickup_name,
            trim(implode(' ', array_filter([$ride->pickup_postal_code, $ride->pickup_city]))),
        ]);

        return $parts ? implode(' - ', $parts) : null;
    }

    private function relatedClubIds(User $user)
    {
        return $user->clubs()
            ->pluck('clubs.id')
            ->merge($this->managedChildren($user)
                ->load(['clubs:id', 'teams:id,club_id'])
                ->flatMap(fn (User $child) => $child->clubs->pluck('id')
                    ->merge($child->teams->pluck('club_id')->filter())))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function relatedTeamIds(User $user)
    {
        return $user->teams()
            ->pluck('teams.id')
            ->merge($this->managedChildren($user)
                ->load('teams:id')
                ->flatMap(fn (User $child) => $child->teams->pluck('id')))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function managedChildren(User $user)
    {
        return User::query()
            ->whereDate('birth_date', '>', now()->subYears(16)->toDateString())
            ->where(function ($query) use ($user) {
                $query->where('guardian_user_id', $user->id)
                    ->orWhere('guardian_email', mb_strtolower((string) $user->email));
            })
            ->get();
    }

    private function pickupPrivateLabel(Ride $ride): ?string
    {
        $street = trim(implode(' ', array_filter([
            $ride->pickup_street,
            $ride->pickup_house_number,
        ])));

        $city = trim(implode(' ', array_filter([
            $ride->pickup_postal_code,
            $ride->pickup_city,
        ])));

        $parts = array_filter([
            $ride->pickup_name,
            $street,
            $city,
            $ride->pickup_country,
            $ride->pickup_note,
        ]);

        return $parts ? implode(', ', $parts) : null;
    }

    private function notifyParticipants(
        Ride $ride,
        string $type,
        string $titleKey,
        string $bodyKey,
        array $replace,
        array $data,
        ?int $excludeUserId = null,
    ): void {
        foreach ($ride->users as $participant) {
            if ((int) $participant->id === (int) $excludeUserId) {
                continue;
            }

            AppNotification::sendLocalized($participant, $type, $titleKey, $bodyKey, $replace, $data);
        }
    }

    private function canManageAllRides(User $user): bool
    {
        return $user->hasAnyRole(Roles::FULL_ACCESS)
            || $user->can('system.manage')
            || $user->can('rides.manage');
    }

    private function resolveJoinState(Ride $ride, User $user, int $acceptedCount, bool $isDriver, bool $isJoined, bool $hasPendingRequest): array
    {
        $canJoinByPolicy = $user->can('join', $ride);
        $isRideFull = $acceptedCount >= (int) $ride->seats;
        $isPastRide = (bool) $ride->departure_time && now()->gt($ride->departure_time);

        $canJoin = $canJoinByPolicy
            && ! $isDriver
            && ! $isJoined
            && ! $hasPendingRequest
            && ! $isRideFull
            && ! $isPastRide;

        if ($canJoin) {
            return [
                'can_join' => true,
                'join_block_reason' => null,
            ];
        }

        return [
            'can_join' => false,
            'join_block_reason' => match (true) {
                $isDriver => 'driver',
                $isJoined => 'already_joined',
                $hasPendingRequest => 'pending_request',
                $isRideFull => 'full',
                $isPastRide => 'past',
                ! $canJoinByPolicy => 'not_allowed',
                default => 'unavailable',
            },
        ];
    }

    private function joinBlockMessage(?string $reason): string
    {
        $key = in_array($reason, ['driver', 'already_joined', 'pending_request', 'full', 'past', 'not_allowed'], true)
            ? $reason
            : 'unavailable';

        return __('rides.join_block.'.$key);
    }
}
