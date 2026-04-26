<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Post;
use App\Services\PostService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PostController extends Controller
{
    public function __construct(private PostService $service) {}

    public function index()
    {
        $user = auth()->user();

        $posts = Post::query()
            ->with([
                'user:id,name',
                'club' => fn ($query) => $query->select('id', 'name'),
                'comments' => fn ($query) => $query
                    ->with('user:id,name')
                    ->withCount('likes')
                    ->latest('id')
                    ->limit(3),
            ])
            ->withCount('comments')
            ->withCount('likes')
            ->withExists([
                'likes as liked_by_me' => fn ($query) => $query->where('user_id', $user->id),
            ])
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Auth/Dashboard/Feed/Index', [
            'posts' => $posts,
            'clubs' => Club::query()
                ->visibleTo($user)
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'club_id' => ['nullable', 'exists:clubs,id'],
            'content' => ['nullable', 'required_without:image', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        if (!empty($data['club_id']) && !auth()->user()->hasRole('super_admin')) {
            abort_unless(auth()->user()->clubs()->where('clubs.id', $data['club_id'])->exists(), 403);
        }

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('posts', 'public');
        }

        $data['content'] ??= '';
        $data['club_id'] = $data['club_id'] ?? null;

        $this->service->create(auth()->user(), $data);

        return back()->with('success', 'Beitrag erstellt.');
    }

    public function update(Request $request, Post $post)
    {
        abort_unless($post->user_id === auth()->id(), 403);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('posts', 'public');
        }

        $post->update($data);

        return back()->with('success', 'Beitrag aktualisiert.');
    }

    public function destroy(Post $post)
    {
        abort_unless($post->user_id === auth()->id(), 403);

        $post->delete();

        return back()->with('success', 'Beitrag gelöscht.');
    }
}
