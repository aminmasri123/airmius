<?php

namespace App\Services;

use App\Models\Ride;

class RideService
{
    public function create($user, $data)
    {
        return Ride::create([
            ...$data,
            'driver_id' => $user->id
        ]);
    }

    public function join($ride, $user)
    {
        $ride->users()->syncWithoutDetaching([$user->id]);
    }
}
