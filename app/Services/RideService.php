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
        $ride->users()->syncWithoutDetaching([
            $user->id => [
                'status' => 'accepted',
                'responded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function requestToJoin($ride, $user, ?string $message = null)
    {
        $ride->users()->syncWithoutDetaching([
            $user->id => [
                'status' => 'requested',
                'message' => $message,
                'responded_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
