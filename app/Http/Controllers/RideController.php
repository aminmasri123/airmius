<?php

namespace App\Http\Controllers;

use App\Models\Ride;
use App\Models\Club;
use App\Models\Team;
use App\Services\RideService;
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
        $clubIds = $user->clubs()->pluck('clubs.id');
        $teamIds = $user->teams()->pluck('teams.id');

        $rides = Ride::query()
            ->with(['driver:id,name', 'users:id,name', 'club:id,name', 'team:id,name'])
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
            })
            ->latest()
            ->get()
            ->map(function (Ride $ride) use ($user) {
                $isDriver = (int) $ride->driver_id === (int) $user->id;
                $isJoined = $ride->users->contains('id', $user->id);
                $canSeePrivateDetails = $isDriver || $isJoined;

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
                    'departure_time' => $ride->departure_time,
                    'seats' => $ride->seats,
                    'contact_details' => $canSeePrivateDetails ? $ride->contact_details : null,
                    'users' => $canSeePrivateDetails ? $ride->users : [],
                    'participants_count' => $ride->users->count(),
                    'is_driver' => $isDriver,
                    'is_joined' => $isJoined,
                    'can_join' => $user->can('join', $ride),
                    'can_delete' => $user->can('delete', $ride),
                ];
            });

        return Inertia::render('Auth/Dashboard/Rides/Index', [
            'rides' => $rides,
            'clubs' => Club::query()
                ->whereHas('users', fn ($query) => $query->where('users.id', $user->id))
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get(),
            'teams' => Team::query()
                ->whereHas('users', fn ($query) => $query->where('users.id', $user->id))
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

        $data = $request->validate([
            'visibility' => ['required', Rule::in(Ride::VISIBILITIES)],
            'club_id' => ['nullable', 'integer', Rule::exists('clubs', 'id')],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')],
            'from' => ['required', 'string', 'max:255'],
            'to' => ['required', 'string', 'max:255'],
            'departure_time' => ['required', 'date'],
            'seats' => ['required', 'integer', 'min:1', 'max:20'],
            'contact_details' => ['nullable', 'string', 'max:1000'],
        ]);
        $request->validate([
            'club_id' => [$data['visibility'] === 'club' ? 'required' : 'nullable'],
            'team_id' => [$data['visibility'] === 'team' ? 'required' : 'nullable'],
        ]);

        if (! empty($data['club_id'])) {
            Club::query()
                ->whereHas('users', fn ($query) => $query->where('users.id', $request->user()->id))
                ->findOrFail($data['club_id']);
        }

        if (! empty($data['team_id'])) {
            Team::query()
                ->whereHas('users', fn ($query) => $query->where('users.id', $request->user()->id))
                ->findOrFail($data['team_id']);
        }

        $ride = $this->service->create($request->user(), $data);
        $this->service->join($ride, $request->user());

        return back()->with('success', 'Fahrgemeinschaft erstellt.');
    }

    public function join(Ride $ride)
    {
        $this->authorize('join', $ride);

        $this->service->join($ride, auth()->user());

        return back()->with('success', 'Du bist der Fahrgemeinschaft beigetreten.');
    }

    public function leave(Ride $ride)
    {
        $ride->users()->detach(auth()->id());

        return back()->with('success', 'Du hast die Fahrgemeinschaft verlassen.');
    }

    public function destroy(Ride $ride)
    {
        $this->authorize('delete', $ride);

        $ride->delete();

        return back()->with('success', 'Fahrgemeinschaft geloescht.');
    }
}
