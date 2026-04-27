<?php

namespace App\Http\Controllers;

use App\Models\Ride;
use App\Services\RideService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RideController extends Controller
{
    public function __construct(private RideService $service) {}

    public function index()
    {$rides = Ride::query()
    ->with('users')
    ->whereHas('users', function ($query) {
        $query->where('users.id', auth()->id());
    })
    ->latest()
    ->get();

        return Inertia::render('Auth/Dashboard/Rides/Index', [
            'rides' => $rides,
        ]);
    }
    public function store(Request $request)
    {
        $this->service->create(auth()->user(), $request->all());
        return back();
    }

    public function join(Ride $ride)
    {
        $this->service->join($ride, auth()->user());
        return back();
    }
}
