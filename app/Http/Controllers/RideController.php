<?php

namespace App\Http\Controllers;

use App\Models\Ride;
use App\Services\RideService;
use Illuminate\Http\Request;

class RideController extends Controller
{
    public function __construct(private RideService $service) {}

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
