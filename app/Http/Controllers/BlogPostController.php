<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\BlogCategory;
use App\Services\MediaOptimizer;
use App\Support\UploadStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class BlogPostController extends Controller
{
    public function __construct(private MediaOptimizer $mediaOptimizer)
    {
    }

    public function index(Request $request)
    {
        $this->authorizeBlog($request, 'blog.view');

        $status = $request->query('status');
        $search = $request->query('search');

        return Inertia::render('Auth/Dashboard/Blogs/Index', [
            'posts' => BlogPost::query()
                ->with(['author:id,name', 'publisher:id,name'])
                ->when($status && $status !== 'all', fn ($query) => $query->where('status', $status))
                ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%");
                }))
                ->latest('updated_at')
                ->paginate(12)
                ->withQueryString(),
            'filters' => [
                'status' => $status ?: 'all',
                'search' => $search ?: '',
            ],
            'categories' => BlogCategory::query()
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
        $data['author_id'] = $request->user()->id;
        $data = $this->prepareCoverImage($request, $data);
        $data = $this->preparePublishingData($request, $data);

        BlogPost::create($data);

        return back()->with('success', 'Blogbeitrag erstellt.');
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

        $data = $this->validated($request, $blogPost);
        $data = $this->prepareCoverImage($request, $data);
        $data = $this->preparePublishingData($request, $data, $blogPost);

        $blogPost->update($data);

        return back()->with('success', 'Blogbeitrag aktualisiert.');
    }

    public function destroy(Request $request, BlogPost $blogPost)
    {
        $this->authorizeBlog($request, 'blog.delete');

        $blogPost->delete();

        return back()->with('success', 'Blogbeitrag gelöscht.');
    }

    public function publicIndex()
    {
        return Inertia::render('Guest/Blog/Index', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'posts' => BlogPost::query()
                ->published()
                ->with('author:id,name')
                ->latest('published_at')
                ->paginate(9),
        ]);
    }

    public function publicShow(BlogPost $blogPost)
    {
        abort_unless($blogPost->status === 'published' && $blogPost->published_at?->lte(now()), 404);

        return Inertia::render('Guest/Blog/Show', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'post' => $blogPost->load('author:id,name'),
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
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'string'],
            'cover_image' => ['nullable', 'url', 'max:2048'],
            'cover_image_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'category' => ['nullable', 'string', 'max:120', Rule::exists('blog_categories', 'name')],
            'tags' => ['nullable', 'string', 'max:500'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['draft', 'review', 'published', 'archived'])],
            'published_at' => ['nullable', 'date'],
        ]);

        $data['slug'] = $data['slug'] ?: BlogPost::uniqueSlug($data['title'], $blogPost?->id);
        $data['content'] = $this->sanitizeContent($data['content']);
        $data['tags'] = collect(explode(',', $data['tags'] ?? ''))
            ->map(fn ($tag) => trim($tag))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $data;
    }

    private function sanitizeContent(string $content): string
    {
        $content = preg_replace('#<(script|style|iframe|object|embed|form|input|button)[^>]*>.*?</\1>#is', '', $content) ?? '';
        $content = strip_tags($content, '<p><br><strong><b><em><i><u><s><strike><h2><h3><h4><blockquote><ul><ol><li><a><span><pre><code><hr><div><figure><figcaption><img>');
        $content = preg_replace('/\s(on[a-z]+|formaction)\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $content) ?? '';
        $content = preg_replace('/\s(href|src)\s*=\s*([\'"])\s*javascript:.*?\2/i', '', $content) ?? '';
        $content = preg_replace('/\sstyle\s*=\s*([\'"]).*?\1/is', '', $content) ?? $content;
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

        $data['published_at'] = $data['published_at'] ?? now();
        $data['published_by'] = $blogPost?->published_by ?: $request->user()->id;

        return $data;
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
