<?php

namespace App\Models;

use App\Support\SupportedLocale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'author_id',
        'published_by',
        'title',
        'slug',
        'content_locale',
        'translation_group',
        'excerpt',
        'content',
        'cover_image',
        'category',
        'blog_category_id',
        'tags',
        'meta_title',
        'meta_description',
        'status',
        'published_at',
    ];

    protected $appends = [
        'reading_time_minutes',
        'seo_score',
    ];

    protected $hidden = [
        'translation_group',
    ];

    protected static function booted(): void
    {
        static::creating(function (BlogPost $post): void {
            $post->content_locale = SupportedLocale::normalize($post->content_locale)
                ?? SupportedLocale::DEFAULT;
            $post->translation_group = $post->translation_group ?: (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function blogCategory(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(BlogPostRevision::class);
    }

    public function latestRevision(): HasOne
    {
        return $this->hasOne(BlogPostRevision::class)->latestOfMany();
    }

    public function translationVariants(): HasMany
    {
        return $this->hasMany(self::class, 'translation_group', 'translation_group')
            ->whereNotNull('translation_group');
    }

    public function scopePublished($query)
    {
        return $query
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopePreferredForLocale(Builder $query, mixed $locale = null): Builder
    {
        $locale = SupportedLocale::normalize($locale ?? app()->getLocale()) ?? SupportedLocale::DEFAULT;

        if ($locale === SupportedLocale::DEFAULT) {
            return $query->where('blog_posts.content_locale', SupportedLocale::DEFAULT);
        }

        return $query->where(function (Builder $preferred) use ($locale): void {
            $preferred
                ->where('blog_posts.content_locale', $locale)
                ->orWhere(function (Builder $fallback) use ($locale): void {
                    $fallback
                        ->where('blog_posts.content_locale', SupportedLocale::DEFAULT)
                        ->whereNotExists(function ($translation) use ($locale): void {
                            $translation
                                ->selectRaw('1')
                                ->from('blog_posts as localized_blog_posts')
                                ->whereColumn(
                                    'localized_blog_posts.translation_group',
                                    'blog_posts.translation_group',
                                )
                                ->where('localized_blog_posts.content_locale', $locale)
                                ->where('localized_blog_posts.status', 'published')
                                ->whereNotNull('localized_blog_posts.published_at')
                                ->where('localized_blog_posts.published_at', '<=', now());
                        });
                });
        });
    }

    public function getReadingTimeMinutesAttribute(): int
    {
        $text = trim(strip_tags((string) $this->content));

        if ($text === '') {
            return 1;
        }

        preg_match_all('/[\p{L}\p{N}]+/u', $text, $matches);

        return max(1, (int) ceil(count($matches[0]) / 220));
    }

    public function getSeoScoreAttribute(): int
    {
        return self::seoScoreFor([
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'cover_image' => $this->cover_image,
            'category' => $this->category,
            'blog_category_id' => $this->blog_category_id,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
        ]);
    }

    public static function seoScoreFor(array $data): int
    {
        $title = (string) ($data['meta_title'] ?? $data['title'] ?? '');
        $description = (string) ($data['meta_description'] ?? $data['excerpt'] ?? '');
        $excerpt = trim((string) ($data['excerpt'] ?? ''));
        $content = trim(strip_tags((string) ($data['content'] ?? '')));
        preg_match_all('/[\p{L}\p{N}]+/u', $content, $matches);

        $checks = [
            mb_strlen($title) >= 35 && mb_strlen($title) <= 65,
            mb_strlen($description) >= 110 && mb_strlen($description) <= 160,
            mb_strlen($excerpt) >= 80,
            count($matches[0]) >= 450,
            filled($data['blog_category_id'] ?? null) || filled($data['category'] ?? null),
            filled($data['cover_image'] ?? null),
        ];

        return (int) round((count(array_filter($checks)) / count($checks)) * 100);
    }

    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: Str::random(8);
        $slug = $base;
        $counter = 2;

        while (self::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
