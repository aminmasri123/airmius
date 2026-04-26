<?php

namespace Database\Seeders;

use App\Models\Club;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ClubsTeamsUsersSeeder extends Seeder
{
    public function run(): void
    {
         // 🔥 Fester User
    $admin = User::updateOrCreate(
        ['email' => 'amin.masri@outlook.com'],
        [
            'name' => 'Amin Masri',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]
    );
    $admin->assignRole('super_admin');

    // 👉 danach Faker Users
    $users = User::factory(100)->create();
        // 👉 USERS
        $users = User::factory(100)->create();

        // 👉 CLUBS
        for ($i = 0; $i < 20; $i++) {

            $owner = $users->random();

            $club = Club::create([
                'name' => fake()->company() . ' FC',
                'owner_id' => $owner->id
            ]);

            // 👉 OWNER
            $club->users()->attach($owner->id, [
                'role' => 'owner'
            ]);

            // 👉 MEMBERS
            $members = $users->where('id', '!=', $owner->id)
                ->random(rand(5, 15));

            $club->users()->attach(
                $members->mapWithKeys(fn($user) => [
                    $user->id => ['role' => 'member']
                ])->toArray()
            );

            // 👉 TEAMS (gesamt ca. 30)
            $teams = Team::factory(rand(1, 3))->create([
                'club_id' => $club->id
            ]);

            foreach ($teams as $team) {

                $teamMembers = $members->random(
                    min($members->count(), rand(3, 8))
                );

                $team->users()->attach(
                    $teamMembers->mapWithKeys(fn($user) => [
                        $user->id => ['role' => 'player']
                    ])->toArray()
                );
            }
        }
    }
}
