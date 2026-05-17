<?php

namespace Database\Factories;

use App\Models\BlogPost;
use App\Models\BlogCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlogPost>
 */
class BlogPostFactory extends Factory
{
    protected $model = BlogPost::class;

    public function configure(): static
    {
        return $this->afterMaking(function (BlogPost $post) {
            if (! $post->blog_category_id && $post->category) {
                $post->blog_category_id = BlogCategory::query()
                    ->where('name', $post->category)
                    ->value('id');
            }
        });
    }

    public function definition(): array
    {
        $title = fake()->sentence(5);

        return [
            'author_id' => User::factory(),
            'published_by' => null,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(6),
            'excerpt' => fake()->sentence(12),
            'content' => '<p>'.fake()->paragraph().'</p>',
            'cover_image' => null,
            'category' => 'Training',
            'blog_category_id' => null,
            'tags' => ['training'],
            'meta_title' => null,
            'meta_description' => null,
            'status' => 'draft',
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_by' => $attributes['author_id'] ?? User::factory(),
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
    }
}
