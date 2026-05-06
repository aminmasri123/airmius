<?php

namespace App\Http\Controllers;

use App\Models\Ride;
use App\Models\Club;
use App\Services\RideService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RideController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private RideService $service) {}

    public function index()
    {
        $this->authorize('viewAny', Ride::class);

        $rides = Ride::query()
            ->with(['driver:id,name', 'users:id,name'])
            ->where(function ($query) {
                $query
                    ->where('driver_id', auth()->id())
                    ->orWhereHas('users', fn ($users) => $users->where('users.id', auth()->id()));
            })
            ->latest()
            ->get()
            ->map(fn (Ride $ride) => [
                'id' => $ride->id,
                'club_id' => $ride->club_id,
                'driver_id' => $ride->driver_id,
                'driver' => $ride->driver,
                'from' => $ride->from,
                'to' => $ride->to,
                'departure_time' => $ride->departure_time,
                'seats' => $ride->seats,
                'users' => $ride->users,
                'participants_count' => $ride->users->count(),
                'is_driver' => (int) $ride->driver_id === (int) auth()->id(),
                'is_joined' => $ride->users->contains('id', auth()->id()),
                'can_delete' => auth()->user()->can('delete', $ride),
            ]);

        return Inertia::render('Auth/Dashboard/Rides/Index', [
            'rides' => $rides,
            'clubs' => Club::query()
                ->whereHas('users', fn ($query) => $query->where('users.id', auth()->id()))
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Ride::class);

        $data = $request->validate([
            'club_id' => ['required', 'integer', 'exists:clubs,id'],
            'from' => ['required', 'string', 'max:255'],
            'to' => ['required', 'string', 'max:255'],
            'departure_time' => ['required', 'date'],
            'seats' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

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
