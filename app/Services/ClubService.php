<?php

namespace App\Services;

use App\Models\Club;
use Illuminate\Support\Facades\DB;

class ClubService
{
    public function create($user, $data)
    {
        return DB::transaction(function () use ($user, $data) {
            $club = Club::create([
                'name' => $data['name'],
                'sport_type' => $data['sport_type'] ?? null,
                'country' => strtoupper($data['country']),
                'street' => $data['street'] ?? null,
                'house_number' => $data['house_number'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'owner_id' => $user->id,
            ]);

            $club->users()->syncWithoutDetaching([
                $user->id => ['role' => 'owner'],
            ]);

            return $club;
        });
    }

    public function delete($club)
    {
        return $club->delete();
    }

    public function update($club, array $data)
    {
        if (isset($data['country'])) {
            $data['country'] = strtoupper($data['country']);
        }

        $club->update($data);

        return $club;
    }
}
