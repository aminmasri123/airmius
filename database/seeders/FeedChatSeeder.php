<?php

namespace Database\Seeders;

use App\Models\Club;
use App\Models\Conversation;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;

class FeedChatSeeder extends Seeder
{
    public function run(): void
    {
        $clubs = Club::query()->with('users')->get();

        foreach ($clubs as $club) {
            $members = $club->users;

            if ($members->isEmpty()) {
                continue;
            }

            Post::factory()
                ->count(fake()->numberBetween(4, 8))
                ->make()
                ->each(function (Post $post) use ($club, $members) {
                    $post->forceFill([
                        'club_id' => $club->id,
                        'user_id' => $members->random()->id,
                    ])->save();

                    $commenters = $members->random(min($members->count(), fake()->numberBetween(1, 5)));
                    foreach ($commenters as $commenter) {
                        $post->comments()->create([
                            'user_id' => $commenter->id,
                            'content' => fake()->sentence(fake()->numberBetween(8, 18)),
                        ]);
                    }

                    $likers = $members->random(min($members->count(), fake()->numberBetween(2, 12)));
                    foreach ($likers as $liker) {
                        $post->likes()->firstOrCreate([
                            'user_id' => $liker->id,
                        ]);
                    }
                });
        }

        $users = User::query()->limit(80)->get();

        if ($users->count() < 3) {
            return;
        }

        for ($i = 0; $i < 25; $i++) {
            $participants = $users->random(2)->values();
            $conversation = Conversation::create(['type' => 'direct']);
            $conversation->users()->attach($participants->pluck('id'));
            $this->seedMessages($conversation, $participants, fake()->numberBetween(3, 12));
        }

        for ($i = 0; $i < 12; $i++) {
            $participants = $users->random(fake()->numberBetween(3, 7))->values();
            $conversation = Conversation::create(['type' => 'group']);
            $conversation->users()->attach($participants->pluck('id'));
            $this->seedMessages($conversation, $participants, fake()->numberBetween(6, 18));
        }
    }

    private function seedMessages(Conversation $conversation, $participants, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $conversation->messages()->create([
                'sender_id' => $participants->random()->id,
                'message' => fake()->sentence(fake()->numberBetween(5, 16)),
                'created_at' => now()->subMinutes(($count - $i) * fake()->numberBetween(4, 35)),
                'updated_at' => now()->subMinutes(($count - $i) * fake()->numberBetween(4, 35)),
            ]);
        }
    }
}
