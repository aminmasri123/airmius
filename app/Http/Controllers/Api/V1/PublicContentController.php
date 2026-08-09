<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Sponsor;
use App\Support\UploadStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicContentController extends Controller
{
    public function blog(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $category = filled($filters['category'] ?? null)
            ? BlogCategory::query()
                ->where('is_active', true)
                ->where(fn ($query) => $query
                    ->where('slug', $filters['category'])
                    ->orWhere('name', $filters['category']))
                ->first()
            : null;

        $posts = BlogPost::query()
            ->published()
            ->with(['author:id,name', 'blogCategory:id,name,slug'])
            ->when($category, fn ($query) => $query->where(function ($nested) use ($category) {
                $nested->where('blog_category_id', $category->id)
                    ->orWhere('category', $category->name);
            }))
            ->when($filters['q'] ?? null, fn ($query, string $search) => $query
                ->where(fn ($nested) => $nested
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%")))
            ->latest('published_at')
            ->paginate($filters['per_page'] ?? 15);

        return response()->json([
            'data' => collect($posts->items())->map(fn (BlogPost $post) => $this->postCard($post))->values(),
            'categories' => BlogCategory::query()
                ->where('is_active', true)
                ->withCount(['posts' => fn ($query) => $query->published()])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    public function blogPost(BlogPost $blogPost): JsonResponse
    {
        abort_unless(
            $blogPost->status === 'published' && $blogPost->published_at?->lte(now()),
            404,
        );
        $blogPost->load(['author:id,name', 'blogCategory:id,name,slug']);
        $related = BlogPost::query()
            ->published()
            ->with(['author:id,name', 'blogCategory:id,name,slug'])
            ->whereKeyNot($blogPost->id)
            ->when($blogPost->blog_category_id || $blogPost->category, fn ($query) => $query
                ->where(function ($nested) use ($blogPost) {
                    $nested
                        ->when($blogPost->blog_category_id, fn ($query) => $query->where('blog_category_id', $blogPost->blog_category_id))
                        ->when($blogPost->category, fn ($query) => $query->orWhere('category', $blogPost->category));
                }))
            ->latest('published_at')
            ->take(3)
            ->get();

        return response()->json([
            'data' => [
                ...$this->postCard($blogPost),
                'content_text' => $this->plainContent($blogPost->content),
            ],
            'related' => $related->map(fn (BlogPost $post) => $this->postCard($post))->values(),
        ]);
    }

    public function sponsors(): JsonResponse
    {
        $sponsors = Sponsor::query()
            ->with('club:id,name')
            ->publiclyVerified()
            ->where(fn ($query) => $query
                ->whereNull('starts_at')
                ->orWhereDate('starts_at', '<=', now()->toDateString()))
            ->where(fn ($query) => $query
                ->whereNull('ends_at')
                ->orWhereDate('ends_at', '>=', now()->toDateString()))
            ->orderByRaw('CASE WHEN club_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('name')
            ->get()
            ->map(fn (Sponsor $sponsor) => [
                'id' => $sponsor->id,
                'name' => $sponsor->name,
                'website' => $this->safeWebsite($sponsor->website),
                'logo_url' => UploadStorage::url($sponsor->logo),
                'logo_light_url' => UploadStorage::url($sponsor->logo_light ?: $sponsor->logo),
                'logo_dark_url' => UploadStorage::url($sponsor->logo_dark ?: $sponsor->logo_light ?: $sponsor->logo),
                'starts_at' => $sponsor->starts_at?->toDateString(),
                'ends_at' => $sponsor->ends_at?->toDateString(),
                'scope' => $sponsor->scope ?: ($sponsor->club_id ? 'club' : 'platform'),
                'club' => $sponsor->club ? [
                    'id' => $sponsor->club->id,
                    'name' => $sponsor->club->name,
                ] : null,
            ])
            ->values();

        return response()->json([
            'data' => $sponsors,
            'stats' => [
                'total' => $sponsors->count(),
                'platform' => $sponsors->where('scope', 'platform')->count(),
                'outfit_subscription' => $sponsors->where('scope', 'outfit_subscription')->count(),
                'club' => $sponsors->where('scope', 'club')->count(),
            ],
        ]);
    }

    private function postCard(BlogPost $post): array
    {
        return [
            'id' => $post->id,
            'slug' => $post->slug,
            'title' => $post->title,
            'excerpt' => $post->excerpt,
            'cover_image_url' => UploadStorage::url($post->cover_image),
            'category' => $post->blogCategory ? [
                'id' => $post->blogCategory->id,
                'name' => $post->blogCategory->name,
                'slug' => $post->blogCategory->slug,
            ] : ($post->category ? ['name' => $post->category] : null),
            'tags' => $post->tags ?: [],
            'author' => $post->author ? ['id' => $post->author->id, 'name' => $post->author->name] : null,
            'reading_time_minutes' => $post->reading_time_minutes,
            'published_at' => $post->published_at?->toIso8601String(),
        ];
    }

    private function plainContent(?string $content): string
    {
        $withBreaks = preg_replace(
            '#</?(p|br|h2|h3|h4|li|blockquote)[^>]*>#i',
            "\n",
            (string) $content,
        ) ?? '';

        return trim(preg_replace('/[ \t]+/u', ' ', html_entity_decode(
            strip_tags($withBreaks),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        )) ?? '');
    }

    private function safeWebsite(?string $website): ?string
    {
        if (! filled($website)) {
            return null;
        }

        $scheme = strtolower((string) parse_url($website, PHP_URL_SCHEME));

        return in_array($scheme, ['https', 'http'], true) ? $website : null;
    }
}
