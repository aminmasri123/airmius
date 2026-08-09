<?php

namespace App\Http\Controllers;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogPostRevision;
use App\Services\BlogTranslationService;
use App\Services\MediaOptimizer;
use App\Support\SupportedLocale;
use App\Support\UploadStorage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class BlogPostController extends Controller
{
    public function __construct(
        private MediaOptimizer $mediaOptimizer,
        private BlogTranslationService $translations,
    ) {}

    public function index(Request $request)
    {
        $this->authorizeBlog($request, 'blog.view');

        $status = $request->query('status');
        $search = $request->query('search');
        $contentLocale = SupportedLocale::normalize($request->query('content_locale'));

        return Inertia::render('Auth/Dashboard/Blogs/Index', [
            'posts' => fn () => BlogPost::query()
                ->with([
                    'author:id,name',
                    'publisher:id,name',
                    'blogCategory:id,name,slug',
                    'translationVariants:id,translation_group,title,slug,content_locale,status,published_at',
                ])
                ->withCount('revisions')
                ->with(['latestRevision' => fn ($query) => $query->select([
                    'blog_post_revisions.id',
                    'blog_post_revisions.blog_post_id',
                    'blog_post_revisions.user_id',
                    'blog_post_revisions.seo_score',
                    'blog_post_revisions.created_at',
                ])])
                ->when($status && $status !== 'all', fn ($query) => $query->where('status', $status))
                ->when($contentLocale, fn ($query) => $query->where('content_locale', $contentLocale))
                ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhereHas('blogCategory', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                }))
                ->latest('updated_at')
                ->paginate(12)
                ->withQueryString(),
            'filters' => [
                'status' => $status ?: 'all',
                'search' => $search ?: '',
                'content_locale' => $contentLocale ?: 'all',
            ],
            'supportedLocales' => $this->translations->locales(),
            'categories' => fn () => BlogCategory::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'can' => [
                'create' => $this->canBlog($request, 'blog.create'),
                'update' => $this->canBlog($request, 'blog.update'),
                'delete' => $this->canBlog($request, 'blog.delete'),
                'publish' => $this->canBlog($request, 'blog.publish'),
                'manageCategories' => $this->canBlog($request, 'blog.manage'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeBlog($request, 'blog.create');

        $data = $this->validated($request);
        $data = $this->translations->prepareForCreate($data, $data['translation_of_id'] ?? null);
        $data['author_id'] = $request->user()->id;
        $data = $this->prepareCoverImage($request, $data);
        $data = $this->preparePublishingData($request, $data);

        try {
            $blogPost = BlogPost::create($data);
        } catch (UniqueConstraintViolationException $exception) {
            $this->translations->rethrowWriteConflict($exception);
        }
        $this->recordRevision($blogPost, $request, ['created']);

        return back()->with('success', __('editorial.responses.blog_post_created'));
    }

    public function uploadContentImage(Request $request)
    {
        abort_unless(
            $this->canBlog($request, 'blog.create') || $this->canBlog($request, 'blog.update'),
            403
        );

        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'alt' => ['nullable', 'string', 'max:160'],
        ]);

        $stored = $this->mediaOptimizer->store($request->file('image'), 'blog/content');

        return response()->json([
            'url' => UploadStorage::url($stored['path']),
            'alt' => $data['alt'] ?? '',
        ]);
    }

    public function update(Request $request, BlogPost $blogPost)
    {
        $this->authorizeBlog($request, 'blog.update');

        $original = $this->revisionSnapshot($blogPost);
        $data = $this->validated($request, $blogPost);
        $data = $this->translations->prepareForUpdate($blogPost, $data);
        $data = $this->prepareCoverImage($request, $data);
        $data = $this->preparePublishingData($request, $data, $blogPost);

        try {
            $blogPost->update($data);
        } catch (UniqueConstraintViolationException $exception) {
            $this->translations->rethrowWriteConflict($exception);
        }
        $changedFields = $this->changedRevisionFields($original, $this->revisionSnapshot($blogPost->fresh()));

        if ($changedFields !== []) {
            $this->recordRevision($blogPost->fresh(), $request, $changedFields);
        }

        return back()->with('success', __('editorial.responses.blog_post_updated'));
    }

    public function destroy(Request $request, BlogPost $blogPost)
    {
        $this->authorizeBlog($request, 'blog.delete');

        $blogPost->delete();

        return back()->with('success', __('editorial.responses.blog_post_deleted'));
    }

    public function publicIndex(Request $request)
    {
        return $this->publicBlogIndexPayload($request);
    }

    public function publicCategory(Request $request, BlogCategory $blogCategory)
    {
        abort_unless($blogCategory->is_active, 404);

        return $this->publicBlogIndexPayload($request, $blogCategory);
    }

    private function publicBlogIndexPayload(Request $request, ?BlogCategory $activeCategory = null)
    {
        $categoryFilter = $request->query('category');
        $search = $request->query('search');

        if (! $activeCategory && $categoryFilter) {
            $activeCategory = BlogCategory::query()
                ->where('is_active', true)
                ->where(function ($query) use ($categoryFilter) {
                    $query->where('slug', $categoryFilter)
                        ->orWhere('name', $categoryFilter);
                })
                ->first();
        }

        $locale = $this->translations->requestedLocale();
        $posts = BlogPost::query()
            ->published()
            ->preferredForLocale($locale)
            ->with(['author:id,name', 'blogCategory:id,name,slug'])
            ->when($activeCategory, fn ($query) => $query->where(function ($query) use ($activeCategory) {
                $query->where('blog_category_id', $activeCategory->id)
                    ->orWhere('category', $activeCategory->name);
            }))
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            }))
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString()
            ->through(fn (BlogPost $post) => $this->translations->decorate($post, $locale));

        return Inertia::render('Guest/Blog/Index', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'posts' => $posts,
            'categories' => BlogCategory::query()
                ->where('is_active', true)
                ->withCount(['posts' => fn ($query) => $query->published()->preferredForLocale($locale)])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'filters' => [
                'category' => $activeCategory?->slug ?: '',
                'search' => $search ?: '',
            ],
            'activeCategory' => $activeCategory,
            'seo' => [
                'title' => $activeCategory
                    ? __('guest_seo.dynamic.blog_category_title', ['category' => $activeCategory->name])
                    : __('guest_seo.pages.blog.title'),
                'description' => $activeCategory?->description
                    ?: __('guest_seo.pages.blog.description'),
                'canonical' => $activeCategory
                    ? route('guest.blog.category', $activeCategory->slug)
                    : route('guest.blog.index'),
                'noindex' => filled($search),
            ],
        ]);
    }

    public function publicShow(BlogPost $blogPost)
    {
        abort_unless($blogPost->status === 'published' && $blogPost->published_at?->lte(now()), 404);

        $localizedVariant = $this->translations->publishedVariantForLocale(
            $blogPost,
            $this->translations->requestedLocale(),
        );
        if ($localizedVariant) {
            return redirect()->to($this->translations->canonicalUrl($localizedVariant), 301);
        }

        $post = $blogPost->load(['author:id,name', 'blogCategory:id,name,slug']);
        $post->content = $this->sanitizeContent((string) $post->content);
        $post = $this->translations->decorate($post);
        $variants = $this->translations->variants($post, true);
        $relatedPosts = $this->relatedPosts($post);

        return Inertia::render('Guest/Blog/Show', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'post' => $post,
            'translations' => $variants,
            'relatedPosts' => $relatedPosts,
            'seo' => [
                'canonical' => $this->translations->canonicalUrl($post),
                'canonical_locale' => $post->content_locale,
                'alternates' => $this->translations->alternates($post),
            ],
        ]);
    }

    public function preview(Request $request, BlogPost $blogPost)
    {
        $this->authorizeBlog($request, 'blog.view');

        $post = $blogPost->load(['author:id,name', 'blogCategory:id,name,slug']);
        $post->content = $this->sanitizeContent((string) $post->content);
        $post = $this->translations->decorate($post);
        $relatedPosts = $this->relatedPosts($post);

        return Inertia::render('Guest/Blog/Show', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'post' => $post,
            'translations' => $this->translations->variants($post),
            'relatedPosts' => $relatedPosts,
            'isPreview' => true,
            'seo' => [
                'canonical' => false,
                'canonical_locale' => $post->content_locale,
                'alternates' => [],
            ],
        ]);
    }

    private function validated(Request $request, ?BlogPost $blogPost = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('blog_posts', 'slug')->ignore($blogPost),
            ],
            'content_locale' => ['sometimes', Rule::in(SupportedLocale::ALL)],
            'translation_of_id' => [
                $blogPost ? 'prohibited' : 'nullable',
                'integer',
                Rule::exists('blog_posts', 'id'),
            ],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'string'],
            'cover_image' => ['nullable', 'url', 'max:2048'],
            'cover_image_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'category' => ['nullable', 'string', 'max:120', Rule::exists('blog_categories', 'name')],
            'blog_category_id' => ['nullable', 'integer', Rule::exists('blog_categories', 'id')->where('is_active', true)],
            'tags' => ['nullable', 'string', 'max:500'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['draft', 'review', 'published', 'archived'])],
            'published_at' => ['nullable', 'date'],
        ]);

        $data['slug'] = $data['slug'] ?: BlogPost::uniqueSlug($data['title'], $blogPost?->id);
        $data = $this->prepareCategoryData($data);
        $data['content'] = $this->sanitizeContent($data['content']);
        $data['tags'] = collect(explode(',', $data['tags'] ?? ''))
            ->map(fn ($tag) => trim($tag))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $data;
    }

    private function relatedPosts(BlogPost $post)
    {
        $locale = $this->translations->requestedLocale();

        return BlogPost::query()
            ->published()
            ->preferredForLocale($locale)
            ->with(['author:id,name', 'blogCategory:id,name,slug'])
            ->whereKeyNot($post->id)
            ->when($post->blog_category_id || $post->category, fn ($query) => $query->where(function ($query) use ($post) {
                $query
                    ->when($post->blog_category_id, fn ($query) => $query->where('blog_category_id', $post->blog_category_id))
                    ->when($post->category, fn ($query) => $query->orWhere('category', $post->category));
            }))
            ->latest('published_at')
            ->take(3)
            ->get()
            ->each(fn (BlogPost $related) => $this->translations->decorate($related, $locale));
    }

    private function prepareCategoryData(array $data): array
    {
        $category = null;

        if (filled($data['blog_category_id'] ?? null)) {
            $category = BlogCategory::query()->find($data['blog_category_id']);
        } elseif (filled($data['category'] ?? null)) {
            $category = BlogCategory::query()
                ->where('name', $data['category'])
                ->orWhere('slug', $data['category'])
                ->first();
        }

        $data['blog_category_id'] = $category?->id;
        $data['category'] = $category?->name;

        return $data;
    }

    private function sanitizeContent(string $content): string
    {
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $content) ?? '';
        $content = preg_replace('#<(script|style|iframe|object|embed|form|input|button)[^>]*(?:>.*?</\1\s*>|/?>)#is', '', $content) ?? '';
        $content = strip_tags($content, '<p><br><strong><b><em><i><u><s><strike><h2><h3><h4><blockquote><ul><ol><li><a><span><pre><code><hr><div><figure><figcaption><img>');
        $content = preg_replace('/\s(on[a-z]+|formaction)\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $content) ?? '';
        $content = preg_replace_callback('/\s(href|src)\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/is', function (array $matches) {
            $rawValue = trim($matches[2], "\"' \t\n\r\0\x0B");
            $decodedValue = strtolower(trim(html_entity_decode($rawValue, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

            if (preg_match('/^(javascript|data|vbscript):/i', $decodedValue)) {
                return '';
            }

            return $matches[0];
        }, $content) ?? '';
        $content = preg_replace('/\sstyle\s*=\s*([\'"]).*?\1/is', '', $content) ?? $content;
        $content = preg_replace('/\s(srcdoc|xmlns|xlink:href|srcset|ping|poster)\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $content) ?? $content;
        $content = preg_replace_callback('/<a\b([^>]*)>/i', function (array $matches) {
            $attributes = $matches[1];

            if (! preg_match('/\btarget\s*=\s*("|\')_blank\1/i', $attributes)) {
                return $matches[0];
            }

            $attributes = preg_replace('/\srel\s*=\s*("|\').*?\1/i', '', $attributes) ?? $attributes;

            return '<a'.$attributes.' rel="noopener noreferrer">';
        }, $content) ?? $content;
        $content = preg_replace_callback('/\sclass\s*=\s*([\'"])(.*?)\1/is', function (array $matches) {
            $allowedClasses = [
                'blog-lead',
                'blog-callout',
                'blog-image',
                'blog-text-primary',
                'blog-text-secondary',
                'blog-text-accent',
                'blog-text-success',
                'blog-text-warning',
                'blog-text-danger',
                'blog-mark',
            ];

            $classes = collect(preg_split('/\s+/', $matches[2]) ?: [])
                ->filter(fn ($class) => in_array($class, $allowedClasses, true))
                ->implode(' ');

            return $classes ? ' class="'.$classes.'"' : '';
        }, $content) ?? $content;

        return trim($content);
    }

    private function prepareCoverImage(Request $request, array $data): array
    {
        unset($data['cover_image_upload']);

        if (! $request->hasFile('cover_image_upload')) {
            return $data;
        }

        $stored = $this->mediaOptimizer->store($request->file('cover_image_upload'), 'blog/covers');
        $data['cover_image'] = UploadStorage::url($stored['path']);

        return $data;
    }

    private function preparePublishingData(Request $request, array $data, ?BlogPost $blogPost = null): array
    {
        if (($data['status'] ?? null) !== 'published') {
            if ($blogPost?->status !== 'published') {
                $data['published_at'] = null;
                $data['published_by'] = null;
            }

            return $data;
        }

        $this->authorizeBlog($request, 'blog.publish');
        $this->ensurePublishable($data);

        $data['published_at'] = $data['published_at'] ?? now();
        $data['published_by'] = $blogPost?->published_by ?: $request->user()->id;

        return $data;
    }

    private function ensurePublishable(array $data): void
    {
        $score = BlogPost::seoScoreFor($data);

        if ($score >= 85) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => __('editorial.errors.quality_required', ['score' => $score]),
        ]);
    }

    private function recordRevision(BlogPost $blogPost, Request $request, array $changedFields): void
    {
        BlogPostRevision::query()->create([
            ...$this->revisionSnapshot($blogPost),
            'blog_post_id' => $blogPost->id,
            'user_id' => $request->user()?->id,
            'seo_score' => $blogPost->seo_score,
            'changed_fields' => array_values($changedFields),
            'created_at' => now(),
        ]);
    }

    private function revisionSnapshot(BlogPost $blogPost): array
    {
        return [
            'title' => $blogPost->title,
            'slug' => $blogPost->slug,
            'content_locale' => $blogPost->content_locale,
            'translation_group' => $blogPost->translation_group,
            'excerpt' => $blogPost->excerpt,
            'content' => $blogPost->content,
            'cover_image' => $blogPost->cover_image,
            'category' => $blogPost->category,
            'blog_category_id' => $blogPost->blog_category_id,
            'tags' => $blogPost->tags,
            'meta_title' => $blogPost->meta_title,
            'meta_description' => $blogPost->meta_description,
            'status' => $blogPost->status,
            'published_at' => $blogPost->published_at,
        ];
    }

    private function changedRevisionFields(array $before, array $after): array
    {
        return collect($after)
            ->filter(function ($value, string $field) use ($before) {
                $oldValue = $before[$field] ?? null;

                if ($oldValue instanceof \DateTimeInterface) {
                    $oldValue = $oldValue->format(DATE_ATOM);
                }

                if ($value instanceof \DateTimeInterface) {
                    $value = $value->format(DATE_ATOM);
                }

                return $oldValue !== $value;
            })
            ->keys()
            ->values()
            ->all();
    }

    private function authorizeBlog(Request $request, string $permission): void
    {
        abort_unless($this->canBlog($request, $permission), 403);
    }

    private function canBlog(Request $request, string $permission): bool
    {
        $user = $request->user();

        return $user?->can($permission)
            || $user?->can('blog.manage')
            || $user?->hasAnyRole(['super_admin', 'admin']);
    }
}
