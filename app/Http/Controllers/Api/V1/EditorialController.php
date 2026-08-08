<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogPostRevision;
use App\Support\UploadStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EditorialController extends Controller
{
    private const POST_CREATED = 'blog_post_created';

    private const POST_UPDATED = 'blog_post_updated';

    private const POST_DELETED = 'blog_post_deleted';

    private const CATEGORY_CREATED = 'blog_category_created';

    private const CATEGORY_UPDATED = 'blog_category_updated';

    private const CATEGORY_DELETED = 'blog_category_deleted';

    public function index(Request $request): JsonResponse
    {
        $this->authorizeBlog($request, 'blog.view');
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['all', 'draft', 'review', 'published', 'archived'])],
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $posts = BlogPost::query()
            ->with(['author:id,name', 'publisher:id,name', 'blogCategory:id,name,slug'])
            ->withCount('revisions')
            ->when(
                filled($filters['status'] ?? null) && $filters['status'] !== 'all',
                fn ($query) => $query->where('status', $filters['status']),
            )
            ->when($filters['q'] ?? null, fn ($query, string $search) => $query
                ->where(fn ($nested) => $nested
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")))
            ->latest('updated_at')
            ->paginate(30);

        return response()->json([
            'data' => collect($posts->items())->map(fn (BlogPost $post) => $this->postData($post))->values(),
            'categories' => BlogCategory::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'description', 'sort_order', 'is_active']),
            'can' => [
                'create' => $this->canBlog($request, 'blog.create'),
                'update' => $this->canBlog($request, 'blog.update'),
                'delete' => $this->canBlog($request, 'blog.delete'),
                'publish' => $this->canBlog($request, 'blog.publish'),
                'manage_categories' => $this->canBlog($request, 'blog.manage'),
            ],
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeBlog($request, 'blog.create');
        $data = $this->validatedPost($request);
        $data['author_id'] = $request->user()->id;
        $data = $this->publishingData($request, $data);
        $post = BlogPost::query()->create($data);
        $this->recordRevision($post, $request, ['created']);

        return response()->json([
            'message' => self::POST_CREATED,
            'message_text' => __('editorial.responses.'.self::POST_CREATED),
            'data' => $this->postData($post->load(['author:id,name', 'publisher:id,name', 'blogCategory:id,name,slug'])),
        ], 201);
    }

    public function update(Request $request, BlogPost $blogPost): JsonResponse
    {
        $this->authorizeBlog($request, 'blog.update');
        $before = $this->snapshot($blogPost);
        $data = $this->publishingData($request, $this->validatedPost($request, $blogPost), $blogPost);
        $blogPost->update($data);
        $fresh = $blogPost->fresh(['author:id,name', 'publisher:id,name', 'blogCategory:id,name,slug']);
        $changed = collect($this->snapshot($fresh))
            ->filter(fn ($value, string $key) => $this->comparable($before[$key] ?? null) !== $this->comparable($value))
            ->keys()
            ->values()
            ->all();
        if ($changed !== []) {
            $this->recordRevision($fresh, $request, $changed);
        }

        return response()->json([
            'message' => self::POST_UPDATED,
            'message_text' => __('editorial.responses.'.self::POST_UPDATED),
            'data' => $this->postData($fresh),
        ]);
    }

    public function destroy(Request $request, BlogPost $blogPost): JsonResponse
    {
        $this->authorizeBlog($request, 'blog.delete');
        $blogPost->delete();

        return response()->json([
            'message' => self::POST_DELETED,
            'message_text' => __('editorial.responses.'.self::POST_DELETED),
        ]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $this->authorizeBlog($request, 'blog.manage');
        $data = $this->validatedCategory($request);
        $data['slug'] = $data['slug'] ?: BlogCategory::uniqueSlug($data['name']);
        $category = BlogCategory::query()->create($data);

        return response()->json([
            'message' => self::CATEGORY_CREATED,
            'message_text' => __('editorial.responses.'.self::CATEGORY_CREATED),
            'data' => $category,
        ], 201);
    }

    public function updateCategory(Request $request, BlogCategory $blogCategory): JsonResponse
    {
        $this->authorizeBlog($request, 'blog.manage');
        $oldName = $blogCategory->name;
        $data = $this->validatedCategory($request, $blogCategory);
        $data['slug'] = $data['slug'] ?: BlogCategory::uniqueSlug($data['name'], $blogCategory->id);
        $blogCategory->update($data);
        if ($oldName !== $blogCategory->name) {
            BlogPost::query()
                ->where('blog_category_id', $blogCategory->id)
                ->update(['category' => $blogCategory->name]);
        }

        return response()->json([
            'message' => self::CATEGORY_UPDATED,
            'message_text' => __('editorial.responses.'.self::CATEGORY_UPDATED),
            'data' => $blogCategory->refresh(),
        ]);
    }

    public function destroyCategory(Request $request, BlogCategory $blogCategory): JsonResponse
    {
        $this->authorizeBlog($request, 'blog.manage');
        abort_if($blogCategory->posts()->exists(), 422, 'blog_category_in_use');
        $blogCategory->delete();

        return response()->json([
            'message' => self::CATEGORY_DELETED,
            'message_text' => __('editorial.responses.'.self::CATEGORY_DELETED),
        ]);
    }

    private function validatedPost(Request $request, ?BlogPost $post = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/', Rule::unique('blog_posts', 'slug')->ignore($post)],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'string', 'max:100000'],
            'cover_image' => ['nullable', 'url:http,https', 'max:2048'],
            'blog_category_id' => ['nullable', 'integer', Rule::exists('blog_categories', 'id')->where('is_active', true)],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:60'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['draft', 'review', 'published', 'archived'])],
            'published_at' => ['nullable', 'date'],
        ]);
        $category = filled($data['blog_category_id'] ?? null)
            ? BlogCategory::query()->find($data['blog_category_id'])
            : null;
        $data['slug'] = ($data['slug'] ?? null) ?: BlogPost::uniqueSlug($data['title'], $post?->id);
        $data['category'] = $category?->name;
        $data['content'] = $this->plainTextToSafeHtml($data['content']);
        $data['tags'] = collect($data['tags'] ?? [])->map(fn ($tag) => trim($tag))->filter()->unique()->values()->all();

        return $data;
    }

    private function publishingData(Request $request, array $data, ?BlogPost $post = null): array
    {
        if ($data['status'] !== 'published') {
            if ($post?->status !== 'published') {
                $data['published_at'] = null;
                $data['published_by'] = null;
            }

            return $data;
        }

        $this->authorizeBlog($request, 'blog.publish');
        $score = BlogPost::seoScoreFor($data);
        if ($score < 85) {
            throw ValidationException::withMessages([
                'status' => [__('editorial.errors.quality_required', ['score' => $score])],
            ]);
        }
        $data['published_at'] = $data['published_at'] ?? now();
        $data['published_by'] = $post?->published_by ?: $request->user()->id;

        return $data;
    }

    private function postData(BlogPost $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'content_text' => trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], "\n", $post->content)), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            'cover_image' => $post->cover_image,
            'cover_image_url' => UploadStorage::url($post->cover_image),
            'blog_category_id' => $post->blog_category_id,
            'category' => $post->blogCategory,
            'tags' => $post->tags ?: [],
            'meta_title' => $post->meta_title,
            'meta_description' => $post->meta_description,
            'status' => $post->status,
            'seo_score' => $post->seo_score,
            'reading_time_minutes' => $post->reading_time_minutes,
            'published_at' => $post->published_at?->toIso8601String(),
            'updated_at' => $post->updated_at?->toIso8601String(),
            'author' => $post->author,
            'publisher' => $post->publisher,
            'revisions_count' => (int) ($post->revisions_count ?? $post->revisions()->count()),
        ];
    }

    private function validatedCategory(Request $request, ?BlogCategory $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('blog_categories', 'name')->ignore($category)],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9-]+$/', Rule::unique('blog_categories', 'slug')->ignore($category)],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function plainTextToSafeHtml(string $content): string
    {
        $paragraphs = preg_split('/\R{2,}/u', trim($content)) ?: [];

        return collect($paragraphs)
            ->map(fn (string $paragraph) => '<p>'.nl2br(e(trim($paragraph)), false).'</p>')
            ->filter(fn (string $paragraph) => $paragraph !== '<p></p>')
            ->implode("\n");
    }

    private function recordRevision(BlogPost $post, Request $request, array $changed): void
    {
        BlogPostRevision::query()->create([
            ...$this->snapshot($post),
            'blog_post_id' => $post->id,
            'user_id' => $request->user()->id,
            'seo_score' => $post->seo_score,
            'changed_fields' => array_values($changed),
            'created_at' => now(),
        ]);
    }

    private function snapshot(BlogPost $post): array
    {
        return [
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'content' => $post->content,
            'cover_image' => $post->cover_image,
            'category' => $post->category,
            'blog_category_id' => $post->blog_category_id,
            'tags' => $post->tags,
            'meta_title' => $post->meta_title,
            'meta_description' => $post->meta_description,
            'status' => $post->status,
            'published_at' => $post->published_at,
        ];
    }

    private function comparable(mixed $value): mixed
    {
        return $value instanceof \DateTimeInterface ? $value->format(DATE_ATOM) : $value;
    }

    private function authorizeBlog(Request $request, string $permission): void
    {
        abort_unless($this->canBlog($request, $permission), 403);
    }

    private function canBlog(Request $request, string $permission): bool
    {
        $user = $request->user();

        return $user->can($permission)
            || $user->can('blog.manage')
            || $user->hasAnyRole(['super_admin', 'admin']);
    }
}
