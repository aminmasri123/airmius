<?php

namespace App\Services;

use App\Models\Ride;

class RideService
{
    public function create($user, $data)
    {
        if (isset($data['pickup_country'])) {
            $data['pickup_country'] = strtoupper($data['pickup_country']);
        }

        return Ride::create([
            ...$data,
            'club_id' => $data['visibility'] === 'club' ? ($data['club_id'] ?? null) : null,
            'team_id' => $data['visibility'] === 'team' ? ($data['team_id'] ?? null) : null,
            'driver_id' => $user->id,
        ]);
    }

    public function join($ride, $user)
    {
        $ride->users()->syncWithoutDetaching([$user->id]);
    }
}
