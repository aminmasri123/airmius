<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Post>
 */
class PostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'content' => fake()->paragraph(fake()->numberBetween(2, 5)),
            'image' => fake()->optional(0.35)->randomElement([
                'https://picsum.photos/seed/airmius-training/1200/800',
                'https://picsum.photos/seed/airmius-match/1200/800',
                'https://picsum.photos/seed/airmius-team/1200/800',
                'https://picsum.photos/seed/airmius-club/1200/800',
            ]),
        ];
    }
}
