<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'club_id' => null,

            'sport_type' => fake()->randomElement([
                'football',
                'basketball',
                'tennis',
                'running',
                'cycling'
            ]),
        ];
    }
}
