<?php

namespace App\Services;

use App\Models\Club;

class ClubService
{
    public function create($user, $data)
    {
        $club = Club::create([
            'name' => $data['name'],
            'owner_id' => $user->id,
        ]);

        $club->users()->attach($user->id, ['role' => 'admin']);

        return $club;
    }

    public function delete($club)
    {
        return $club->delete();
    }
}
