<?php

namespace App\Http\Controllers;

use App\Models\Ride;
use App\Models\Club;
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
            ->latest()
            ->get()
            ->map(function (Ride $ride) use ($user) {
                $isDriver = (int) $ride->driver_id === (int) $user->id;
                $acceptedUsers = $ride->users->filter(fn (User $member) => $member->pivot?->status === 'accepted')->values();
                $pendingUsers = $ride->users->filter(fn (User $member) => $member->pivot?->status === 'requested')->values();
                $ownPivot = $ride->users->firstWhere('id', $user->id)?->pivot;
                $isJoined = $ownPivot?->status === 'accepted';
                $hasPendingRequest = $ownPivot?->status === 'requested';
                $canUpdate = $user->can('update', $ride);
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
                    'can_join' => ! $isDriver
                        && ! $isJoined
                        && ! $hasPendingRequest
                        && $acceptedUsers->count() < (int) $ride->seats
                        && $user->can('join', $ride),
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
                ['value' => 'friends', 'label' => 'Nur Freunde', 'description' => 'Datenschutzfreundlich: sichtbar fuer deine Freunde.'],
                ['value' => 'club', 'label' => 'Nur Verein', 'description' => 'Sichtbar fuer Mitglieder des ausgewaehlten Vereins.'],
                ['value' => 'team', 'label' => 'Nur Team', 'description' => 'Sichtbar fuer Mitglieder des ausgewaehlten Teams.'],
                ['value' => 'public', 'label' => 'Oeffentlich', 'description' => 'Sichtbar fuer alle eingeloggten Nutzer. Kontaktdaten bleiben bis zum Beitritt verborgen.'],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Ride::class);

        $data = $this->validatedRideData($request);

        $ride = $this->service->create($request->user(), $data);
        $this->service->join($ride, $request->user());

        return back()->with('success', 'Fahrgemeinschaft erstellt.');
    }

    public function update(Request $request, Ride $ride)
    {
        $this->authorize('update', $ride);

        $data = $this->validatedRideData($request);
        $participantsCount = $ride->acceptedUsers()->count();

        abort_if((int) $data['seats'] < $participantsCount, 422, 'Die Plaetze duerfen nicht unter der aktuellen Mitfahrerzahl liegen.');

        $ride->update([
            ...$data,
            'club_id' => $data['visibility'] === 'club' ? ($data['club_id'] ?? null) : ($data['visibility'] === 'team' ? ($data['club_id'] ?? null) : null),
            'team_id' => $data['visibility'] === 'team' ? ($data['team_id'] ?? null) : null,
        ]);

        $this->notifyParticipants($ride->fresh(['users', 'driver']), 'ride.updated', [
            'title' => 'Fahrgemeinschaft wurde bearbeitet',
            'message' => $ride->from.' -> '.$ride->to.' wurde aktualisiert.',
            'ride_id' => $ride->id,
            'url' => route('auth.rides.index'),
        ], excludeUserId: $request->user()->id);

        return back()->with('success', 'Fahrgemeinschaft aktualisiert.');
    }

    public function join(Ride $ride)
    {
        $this->authorize('join', $ride);

        $data = request()->validate([
            'message' => ['nullable', 'string', 'max:500'],
        ]);
        $alreadyRequestedOrJoined = $ride->users()
            ->where('users.id', auth()->id())
            ->wherePivotIn('status', ['requested', 'accepted'])
            ->exists();
        abort_if(! $alreadyRequestedOrJoined && $ride->acceptedUsers()->count() >= (int) $ride->seats, 422, 'Diese Fahrgemeinschaft ist bereits voll.');

        $this->service->requestToJoin($ride, auth()->user(), $data['message'] ?? null);

        if (! $alreadyRequestedOrJoined && (int) $ride->driver_id !== (int) auth()->id()) {
            AppNotification::send($ride->driver_id, 'ride.requested', [
                'title' => 'Neue Mitfahranfrage',
                'message' => auth()->user()->name.' moechte bei '.$ride->from.' -> '.$ride->to.' mitfahren.',
                'ride_id' => $ride->id,
                'url' => route('auth.rides.index'),
            ]);
        }

        return back()->with('success', 'Deine Mitfahranfrage wurde gesendet.');
    }

    public function leave(Ride $ride)
    {
        $user = auth()->user();
        abort_if((int) $ride->driver_id === (int) $user->id, 422, 'Fahrer koennen ihre eigene Fahrt nicht verlassen. Bitte loesche die Fahrt stattdessen.');

        $wasJoined = $ride->users()->where('users.id', $user->id)->wherePivot('status', 'accepted')->exists();
        $ride->users()->detach($user->id);

        if ($wasJoined) {
            AppNotification::send($ride->driver_id, 'ride.left', [
                'title' => 'Mitfahrt verlassen',
                'message' => $user->name.' hat deine Fahrgemeinschaft '.$ride->from.' -> '.$ride->to.' verlassen.',
                'ride_id' => $ride->id,
                'url' => route('auth.rides.index'),
            ]);
        }

        return back()->with('success', 'Du hast die Fahrgemeinschaft verlassen.');
    }

    public function approveRequest(Ride $ride, User $user)
    {
        $this->authorize('update', $ride);
        abort_unless($ride->users()->where('users.id', $user->id)->wherePivot('status', 'requested')->exists(), 404);
        abort_if($ride->acceptedUsers()->count() >= (int) $ride->seats, 422, 'Diese Fahrgemeinschaft ist bereits voll.');

        $ride->users()->updateExistingPivot($user->id, [
            'status' => 'accepted',
            'responded_at' => now(),
            'updated_at' => now(),
        ]);

        AppNotification::send($user, 'ride.request_approved', [
            'title' => 'Mitfahranfrage angenommen',
            'message' => 'Deine Anfrage fuer '.$ride->from.' -> '.$ride->to.' wurde angenommen.',
            'ride_id' => $ride->id,
            'url' => route('auth.rides.index'),
        ]);

        return back()->with('success', 'Mitfahranfrage angenommen.');
    }

    public function rejectRequest(Ride $ride, User $user)
    {
        $this->authorize('update', $ride);
        abort_unless($ride->users()->where('users.id', $user->id)->wherePivot('status', 'requested')->exists(), 404);

        $ride->users()->updateExistingPivot($user->id, [
            'status' => 'rejected',
            'responded_at' => now(),
            'updated_at' => now(),
        ]);

        AppNotification::send($user, 'ride.request_rejected', [
            'title' => 'Mitfahranfrage abgelehnt',
            'message' => 'Deine Anfrage fuer '.$ride->from.' -> '.$ride->to.' wurde abgelehnt.',
            'ride_id' => $ride->id,
            'url' => route('auth.rides.index'),
        ]);

        return back()->with('success', 'Mitfahranfrage abgelehnt.');
    }

    public function removeMember(Ride $ride, User $user)
    {
        $this->authorize('update', $ride);
        abort_if((int) $ride->driver_id === (int) $user->id, 422, 'Der Fahrer kann nicht aus seiner eigenen Fahrgemeinschaft entfernt werden.');
        abort_unless($ride->users()->where('users.id', $user->id)->wherePivot('status', 'accepted')->exists(), 404);

        $ride->users()->detach($user->id);

        AppNotification::send($user, 'ride.member_removed', [
            'title' => 'Aus Fahrgemeinschaft entfernt',
            'message' => 'Du wurdest aus der Fahrgemeinschaft '.$ride->from.' -> '.$ride->to.' entfernt.',
            'ride_id' => $ride->id,
            'url' => route('auth.rides.index'),
        ]);

        return back()->with('success', 'Mitfahrer wurde entfernt.');
    }

    public function destroy(Ride $ride)
    {
        $this->authorize('delete', $ride);
        $participants = $ride->users()->where('users.id', '!=', $ride->driver_id)->wherePivotIn('status', ['requested', 'accepted'])->get();
        $message = $ride->from.' -> '.$ride->to.' wurde geloescht.';

        foreach ($participants as $participant) {
            AppNotification::send($participant, 'ride.deleted', [
                'title' => 'Fahrgemeinschaft geloescht',
                'message' => $message,
                'ride_id' => $ride->id,
                'url' => route('auth.rides.index'),
            ]);
        }

        $ride->delete();

        return back()->with('success', 'Fahrgemeinschaft geloescht.');
    }

    private function validatedRideData(Request $request): array
    {
        $data = $request->validate([
            'visibility' => ['required', Rule::in(Ride::VISIBILITIES)],
            'club_id' => ['nullable', 'integer', Rule::exists('clubs', 'id')],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')],
            'from' => ['required', 'string', 'max:255'],
            'to' => ['required', 'string', 'max:255'],
            'pickup_name' => ['nullable', 'string', 'max:255'],
            'pickup_street' => ['nullable', 'string', 'max:255'],
            'pickup_house_number' => ['nullable', 'string', 'max:40'],
            'pickup_postal_code' => ['nullable', 'string', 'max:30'],
            'pickup_city' => ['nullable', 'string', 'max:255'],
            'pickup_country' => ['nullable', 'string', 'size:2'],
            'pickup_note' => ['nullable', 'string', 'max:500'],
            'departure_time' => ['required', 'date'],
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
                    ->orWhereRaw('LOWER(guardian_email) = ?', [mb_strtolower((string) $user->email)]);
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

    private function notifyParticipants(Ride $ride, string $type, array $data, ?int $excludeUserId = null): void
    {
        foreach ($ride->users as $participant) {
            if ((int) $participant->id === (int) $excludeUserId) {
                continue;
            }

            AppNotification::send($participant, $type, $data);
        }
    }

    private function canManageAllRides(User $user): bool
    {
        return $user->hasAnyRole(Roles::FULL_ACCESS)
            || $user->can('system.manage')
            || $user->can('rides.manage');
    }
}
