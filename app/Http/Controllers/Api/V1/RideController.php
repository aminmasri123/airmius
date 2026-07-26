<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\Ride;
use App\Models\Team;
use App\Models\User;
use App\Services\RideService;
use App\Support\AppNotification;
use App\Support\Roles;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RideController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private RideService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Ride::class);

        $user = $request->user();
        $clubIds = $this->relatedClubIds($user);
        $teamIds = $this->relatedTeamIds($user);
        $friendIds = $user->friendships()->pluck('friend_id');

        $rides = Ride::query()
            ->with(['driver:id,name', 'users:id,name', 'club:id,name', 'team:id,name,club_id'])
            ->when(! $this->canManageAllRides($user), function ($query) use ($user, $friendIds, $clubIds, $teamIds) {
                $query->where(function ($visible) use ($user, $friendIds, $clubIds, $teamIds) {
                    $visible
                        ->where('driver_id', $user->id)
                        ->orWhereHas('users', fn ($members) => $members->where('users.id', $user->id))
                        ->orWhere('visibility', 'public')
                        ->orWhere(fn ($friends) => $friends
                            ->where('visibility', 'friends')
                            ->whereIn('driver_id', $friendIds))
                        ->orWhere(fn ($clubs) => $clubs
                            ->where('visibility', 'club')
                            ->whereIn('club_id', $clubIds))
                        ->orWhere(fn ($teams) => $teams
                            ->where('visibility', 'team')
                            ->whereIn('team_id', $teamIds));
                });
            })
            ->orderBy('departure_time')
            ->latest('id')
            ->get()
            ->map(fn (Ride $ride) => $this->rideData($ride, $user))
            ->values();

        return response()->json([
            'data' => [
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
                'visibilities' => Ride::VISIBILITIES,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Ride::class);

        $data = $this->validatedRideData($request);
        $ride = $this->service->create($request->user(), $data);
        $this->service->join($ride, $request->user());

        return response()->json([
            'message' => 'ride_created',
            'data' => $this->freshRideData($ride, $request->user()),
        ], 201);
    }

    public function update(Request $request, Ride $ride): JsonResponse
    {
        $this->authorize('update', $ride);

        $data = $this->validatedRideData($request);
        abort_if(
            (int) $data['seats'] < $ride->acceptedUsers()->count(),
            422,
            'ride_seats_below_participants',
        );

        $ride->update([
            ...$data,
            'club_id' => in_array($data['visibility'], ['club', 'team'], true)
                ? ($data['club_id'] ?? null)
                : null,
            'team_id' => $data['visibility'] === 'team' ? ($data['team_id'] ?? null) : null,
        ]);

        $ride->load(['users', 'driver']);
        $this->notifyParticipants($ride, 'ride.updated', [
            'title' => 'Fahrgemeinschaft wurde bearbeitet',
            'message' => $ride->from.' -> '.$ride->to.' wurde aktualisiert.',
            'ride_id' => $ride->id,
            'url' => route('auth.rides.index'),
        ], $request->user()->id);

        return response()->json([
            'message' => 'ride_updated',
            'data' => $this->freshRideData($ride, $request->user()),
        ]);
    }

    public function join(Request $request, Ride $ride): JsonResponse
    {
        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:500'],
        ]);
        $ride->loadMissing('users:id,name');
        $user = $request->user();
        $accepted = $ride->users
            ->filter(fn (User $member) => $member->pivot?->status === Ride::MEMBER_STATUS_ACCEPTED);
        $ownPivot = $ride->users->firstWhere('id', $user->id)?->pivot;
        $joinState = $this->resolveJoinState(
            $ride,
            $user,
            $accepted->count(),
            (int) $ride->driver_id === (int) $user->id,
            $ownPivot?->status === Ride::MEMBER_STATUS_ACCEPTED,
            $ownPivot?->status === Ride::MEMBER_STATUS_REQUESTED,
        );

        abort_unless($joinState['can_join'], 422, $joinState['join_block_reason'] ?? 'unavailable');

        $result = $this->service->requestToJoin($ride, $user, $data['message'] ?? null);
        abort_if($result === RideService::RESULT_FULL, 422, 'full');
        abort_unless(
            in_array($result, [
                RideService::RESULT_REQUESTED,
                RideService::RESULT_ALREADY_JOINED,
                RideService::RESULT_ALREADY_REQUESTED,
            ], true),
            422,
            'unavailable',
        );

        if ($result === RideService::RESULT_REQUESTED) {
            AppNotification::send($ride->driver_id, 'ride.requested', [
                'title' => 'Neue Mitfahranfrage',
                'message' => $user->name.' möchte bei '.$ride->from.' -> '.$ride->to.' mitfahren.',
                'ride_id' => $ride->id,
                'url' => route('auth.rides.index'),
            ]);
        }

        return response()->json([
            'message' => $result,
            'data' => $this->freshRideData($ride, $user),
        ]);
    }

    public function leave(Request $request, Ride $ride): JsonResponse
    {
        $user = $request->user();
        abort_if((int) $ride->driver_id === (int) $user->id, 422, 'driver_cannot_leave');

        $pivot = $ride->users()->where('users.id', $user->id)->first()?->pivot;
        abort_unless($pivot, 422, 'not_a_participant');
        abort_unless(
            in_array($pivot->status, [Ride::MEMBER_STATUS_REQUESTED, Ride::MEMBER_STATUS_ACCEPTED], true),
            422,
            'membership_action_unavailable',
        );

        $wasAccepted = $pivot->status === Ride::MEMBER_STATUS_ACCEPTED;
        $ride->users()->detach($user->id);

        if ($wasAccepted) {
            AppNotification::send($ride->driver_id, 'ride.left', [
                'title' => 'Mitfahrt verlassen',
                'message' => $user->name.' hat deine Fahrgemeinschaft '.$ride->from.' -> '.$ride->to.' verlassen.',
                'ride_id' => $ride->id,
                'url' => route('auth.rides.index'),
            ]);
        }

        return response()->json([
            'message' => $wasAccepted ? 'ride_left' : 'ride_request_withdrawn',
            'data' => $this->freshRideData($ride, $user),
        ]);
    }

    public function approveRequest(Request $request, Ride $ride, User $user): JsonResponse
    {
        $this->authorize('update', $ride);

        $result = $this->service->approveRequest($ride, $user);
        abort_if($result === RideService::RESULT_FULL, 422, 'full');
        abort_unless($result === RideService::RESULT_APPROVED, 422, 'request_not_found');

        AppNotification::send($user, 'ride.request_approved', [
            'title' => 'Mitfahranfrage angenommen',
            'message' => 'Deine Anfrage für '.$ride->from.' -> '.$ride->to.' wurde angenommen.',
            'ride_id' => $ride->id,
            'url' => route('auth.rides.index'),
        ]);

        return response()->json([
            'message' => 'request_approved',
            'data' => $this->freshRideData($ride, $request->user()),
        ]);
    }

    public function rejectRequest(Request $request, Ride $ride, User $user): JsonResponse
    {
        $this->authorize('update', $ride);
        abort_unless($this->service->rejectRequest($ride, $user), 422, 'request_not_found');

        AppNotification::send($user, 'ride.request_rejected', [
            'title' => 'Mitfahranfrage abgelehnt',
            'message' => 'Deine Anfrage für '.$ride->from.' -> '.$ride->to.' wurde abgelehnt.',
            'ride_id' => $ride->id,
            'url' => route('auth.rides.index'),
        ]);

        return response()->json([
            'message' => 'request_rejected',
            'data' => $this->freshRideData($ride, $request->user()),
        ]);
    }

    public function removeMember(Request $request, Ride $ride, User $user): JsonResponse
    {
        $this->authorize('update', $ride);
        abort_if((int) $user->id === (int) $ride->driver_id, 422, 'driver_cannot_be_removed');

        $pivot = $ride->users()->where('users.id', $user->id)->first()?->pivot;
        abort_unless($pivot?->status === Ride::MEMBER_STATUS_ACCEPTED, 422, 'accepted_member_not_found');

        $ride->users()->detach($user->id);
        AppNotification::send($user, 'ride.member_removed', [
            'title' => 'Aus Fahrgemeinschaft entfernt',
            'message' => 'Du wurdest aus der Fahrgemeinschaft '.$ride->from.' -> '.$ride->to.' entfernt.',
            'ride_id' => $ride->id,
            'url' => route('auth.rides.index'),
        ]);

        return response()->json([
            'message' => 'member_removed',
            'data' => $this->freshRideData($ride, $request->user()),
        ]);
    }

    public function destroy(Request $request, Ride $ride): JsonResponse
    {
        $this->authorize('delete', $ride);

        $participants = $ride->users()
            ->where('users.id', '!=', $ride->driver_id)
            ->wherePivotIn('status', [Ride::MEMBER_STATUS_REQUESTED, Ride::MEMBER_STATUS_ACCEPTED])
            ->get();

        foreach ($participants as $participant) {
            AppNotification::send($participant, 'ride.deleted', [
                'title' => 'Fahrgemeinschaft gelöscht',
                'message' => $ride->from.' -> '.$ride->to.' wurde gelöscht.',
                'ride_id' => $ride->id,
                'url' => route('auth.rides.index'),
            ]);
        }

        $ride->delete();

        return response()->json(['message' => 'ride_deleted']);
    }

    private function freshRideData(Ride $ride, User $user): array
    {
        return $this->rideData(
            $ride->fresh(['driver:id,name', 'users:id,name', 'club:id,name', 'team:id,name,club_id']),
            $user,
        );
    }

    private function rideData(Ride $ride, User $user): array
    {
        $accepted = $ride->users
            ->filter(fn (User $member) => $member->pivot?->status === Ride::MEMBER_STATUS_ACCEPTED)
            ->values();
        $pending = $ride->users
            ->filter(fn (User $member) => $member->pivot?->status === Ride::MEMBER_STATUS_REQUESTED)
            ->values();
        $ownPivot = $ride->users->firstWhere('id', $user->id)?->pivot;
        $isDriver = (int) $ride->driver_id === (int) $user->id;
        $isJoined = $ownPivot?->status === Ride::MEMBER_STATUS_ACCEPTED;
        $hasPending = $ownPivot?->status === Ride::MEMBER_STATUS_REQUESTED;
        $canUpdate = $user->can('update', $ride);
        $canSeePrivate = $isDriver || $isJoined || $canUpdate;
        $joinState = $this->resolveJoinState(
            $ride,
            $user,
            $accepted->count(),
            $isDriver,
            $isJoined,
            $hasPending,
        );

        return [
            'id' => $ride->id,
            'club_id' => $ride->club_id,
            'team_id' => $ride->team_id,
            'club' => $ride->club ? ['id' => $ride->club->id, 'name' => $ride->club->name] : null,
            'team' => $ride->team ? ['id' => $ride->team->id, 'name' => $ride->team->name] : null,
            'driver_id' => $ride->driver_id,
            'driver' => $ride->driver ? ['id' => $ride->driver->id, 'name' => $ride->driver->name] : null,
            'visibility' => $ride->visibility,
            'from' => $ride->from,
            'to' => $ride->to,
            'pickup_name' => $ride->pickup_name,
            'pickup_street' => $canSeePrivate ? $ride->pickup_street : null,
            'pickup_house_number' => $canSeePrivate ? $ride->pickup_house_number : null,
            'pickup_postal_code' => $ride->pickup_postal_code,
            'pickup_city' => $ride->pickup_city,
            'pickup_country' => $ride->pickup_country,
            'pickup_note' => $canSeePrivate ? $ride->pickup_note : null,
            'pickup_public_label' => $this->pickupPublicLabel($ride),
            'pickup_private_label' => $canSeePrivate ? $this->pickupPrivateLabel($ride) : null,
            'departure_time' => $ride->departure_time?->toIso8601String(),
            'seats' => (int) $ride->seats,
            'contact_details' => $canSeePrivate ? $ride->contact_details : null,
            'users' => $canSeePrivate ? $accepted->map(fn (User $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'can_remove' => $canUpdate && (int) $member->id !== (int) $ride->driver_id,
            ])->values() : [],
            'participants_count' => $accepted->count(),
            'pending_requests' => $isDriver ? $pending->map(fn (User $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'message' => $member->pivot?->message,
                'requested_at' => $member->pivot?->created_at?->toIso8601String(),
            ])->values() : [],
            'is_driver' => $isDriver,
            'is_joined' => $isJoined,
            'has_pending_request' => $hasPending,
            'can_join' => $joinState['can_join'],
            'join_block_reason' => $joinState['join_block_reason'],
            'can_update' => $canUpdate,
            'can_delete' => $user->can('delete', $ride),
        ];
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

        abort_if(! empty($data['club_id']) && ! $clubIds->contains((int) $data['club_id']), 403);
        abort_if(! empty($data['team_id']) && ! $teamIds->contains((int) $data['team_id']), 403);

        if (! empty($data['team_id'])) {
            $team = Team::query()->select(['id', 'club_id'])->findOrFail($data['team_id']);
            abort_if(! empty($data['club_id']) && (int) $data['club_id'] !== (int) $team->club_id, 422, 'team_club_mismatch');
            $data['club_id'] = $team->club_id;
        }

        if (isset($data['pickup_country'])) {
            $data['pickup_country'] = strtoupper((string) $data['pickup_country']);
        }

        return $data;
    }

    private function relatedClubIds(User $user)
    {
        return $user->clubs()
            ->pluck('clubs.id')
            ->merge($this->managedChildren($user)
                ->with(['clubs:id', 'teams:id,club_id'])
                ->get()
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
                ->with('teams:id')
                ->get()
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
            });
    }

    private function canManageAllRides(User $user): bool
    {
        return $user->hasAnyRole(Roles::FULL_ACCESS)
            || $user->can('system.manage')
            || $user->can('rides.manage');
    }

    private function resolveJoinState(
        Ride $ride,
        User $user,
        int $acceptedCount,
        bool $isDriver,
        bool $isJoined,
        bool $hasPending,
    ): array {
        $canJoinByPolicy = $user->can('join', $ride);
        $full = $acceptedCount >= (int) $ride->seats;
        $past = (bool) $ride->departure_time && now()->gt($ride->departure_time);
        $canJoin = $canJoinByPolicy && ! $isDriver && ! $isJoined && ! $hasPending && ! $full && ! $past;

        return [
            'can_join' => $canJoin,
            'join_block_reason' => $canJoin ? null : match (true) {
                $isDriver => 'driver',
                $isJoined => 'already_joined',
                $hasPending => 'pending_request',
                $full => 'full',
                $past => 'past',
                ! $canJoinByPolicy => 'not_allowed',
                default => 'unavailable',
            },
        ];
    }

    private function pickupPublicLabel(Ride $ride): ?string
    {
        $parts = array_filter([
            $ride->pickup_name,
            trim(implode(' ', array_filter([$ride->pickup_postal_code, $ride->pickup_city]))),
        ]);

        return $parts ? implode(' - ', $parts) : null;
    }

    private function pickupPrivateLabel(Ride $ride): ?string
    {
        $parts = array_filter([
            $ride->pickup_name,
            trim(implode(' ', array_filter([$ride->pickup_street, $ride->pickup_house_number]))),
            trim(implode(' ', array_filter([$ride->pickup_postal_code, $ride->pickup_city]))),
            $ride->pickup_country,
            $ride->pickup_note,
        ]);

        return $parts ? implode(', ', $parts) : null;
    }

    private function notifyParticipants(Ride $ride, string $type, array $data, ?int $excludeUserId = null): void
    {
        foreach ($ride->users as $participant) {
            if ((int) $participant->id !== (int) $excludeUserId) {
                AppNotification::send($participant, $type, $data);
            }
        }
    }
}
