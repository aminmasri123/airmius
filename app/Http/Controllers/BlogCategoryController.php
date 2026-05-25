<?php

namespace App\Http\Controllers;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class BlogCategoryController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeBlog($request);

        return Inertia::render('Auth/Dashboard/Blogs/Categories', [
            'categories' => BlogCategory::query()
                ->withCount('posts')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(20),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeBlog($request);

        $data = $this->validated($request);
        $data['slug'] = $data['slug'] ?: BlogCategory::uniqueSlug($data['name']);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $data['is_active'] ?? false;

        BlogCategory::create($data);

        return back()->with('success', 'Blog-Kategorie erstellt.');
    }

    public function update(Request $request, BlogCategory $blogCategory)
    {
        $this->authorizeBlog($request);

        $oldName = $blogCategory->name;
        $data = $this->validated($request, $blogCategory);
        $data['slug'] = $data['slug'] ?: BlogCategory::uniqueSlug($data['name'], $blogCategory->id);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $data['is_active'] ?? false;

        $blogCategory->update($data);

        if ($oldName !== $blogCategory->name) {
            BlogPost::query()
                ->where('category', $oldName)
                ->update(['category' => $blogCategory->name]);
        }

        return back()->with('success', 'Blog-Kategorie aktualisiert.');
    }

    public function destroy(Request $request, BlogCategory $blogCategory)
    {
        $this->authorizeBlog($request);

        if (BlogPost::query()->where('category', $blogCategory->name)->exists()) {
            return back()->withErrors([
                'category' => 'Diese Kategorie wird noch von Blogbeiträgen verwendet.',
            ]);
        }

        $blogCategory->delete();

        return back()->with('success', 'Blog-Kategorie gelöscht.');
    }

    private function validated(Request $request, ?BlogCategory $blogCategory = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('blog_categories', 'name')->ignore($blogCategory),
            ],
            'slug' => [
                'nullable',
                'string',
                'max:140',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('blog_categories', 'slug')->ignore($blogCategory),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['boolean'],
        ]);
    }

    private function authorizeBlog(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user?->can('blog.manage') || $user?->hasAnyRole(['super_admin', 'admin']),
            403
        );
    }
}
