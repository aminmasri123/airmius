<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class BlogPostController extends Controller
{
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
            'can' => [
                'create' => $this->canBlog($request, 'blog.create'),
                'update' => $this->canBlog($request, 'blog.update'),
                'delete' => $this->canBlog($request, 'blog.delete'),
                'publish' => $this->canBlog($request, 'blog.publish'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeBlog($request, 'blog.create');

        $data = $this->validated($request);
        $data['author_id'] = $request->user()->id;
        $data = $this->preparePublishingData($request, $data);

        BlogPost::create($data);

        return back()->with('success', 'Blogbeitrag erstellt.');
    }

    public function update(Request $request, BlogPost $blogPost)
    {
        $this->authorizeBlog($request, 'blog.update');

        $data = $this->validated($request, $blogPost);
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
            'category' => ['nullable', 'string', 'max:120'],
            'tags' => ['nullable', 'string', 'max:500'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['draft', 'review', 'published', 'archived'])],
            'published_at' => ['nullable', 'date'],
        ]);

        $data['slug'] = $data['slug'] ?: BlogPost::uniqueSlug($data['title'], $blogPost?->id);
        $data['tags'] = collect(explode(',', $data['tags'] ?? ''))
            ->map(fn ($tag) => trim($tag))
            ->filter()
            ->unique()
            ->values()
            ->all();

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
