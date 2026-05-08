<?php

namespace App\Http\Controllers;

use App\Models\Ride;
use App\Models\Club;
use App\Models\Team;
use App\Models\User;
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
        $clubIds = $this->relatedClubIds($user);
        $teamIds = $this->relatedTeamIds($user);

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
}
