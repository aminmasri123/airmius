<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Activity;
use App\Models\Post;
use App\Models\Team;
use App\Services\PostService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PostController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private PostService $service) {}

    public function index()
    {
        $user = auth()->user();

        $posts = Post::query()
            ->where(function ($query) use ($user) {
                $query->where('visibility', 'public')
                    ->orWhere(function ($query) use ($user) {
                        $query->where('visibility', 'organization')
                            ->whereHas('club.users', fn ($q) => $q->where('users.id', $user->id));
                    })
                    ->orWhere(function ($query) use ($user) {
                        $query->where('visibility', 'team')
                            ->whereHas('team.users', fn ($q) => $q->where('users.id', $user->id));
                    })
                    ->orWhere('user_id', $user->id);
            })
            ->with([
                'user:id,name',
                'club' => fn ($query) => $query->select('id', 'name'),
                'team' => fn ($query) => $query->select('id', 'name', 'club_id'),
                'attachments.file:id,path,type,size',
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
            'teams' => Team::query()
                ->visibleTo($user)
                ->select(['id', 'club_id', 'name'])
                ->orderBy('name')
                ->get(),
            'visibilities' => Post::VISIBILITIES,
            'activities' => Activity::query()
                ->with('user:id,name')
                ->where(function ($query) use ($user) {
                    $query->whereNull('club_id')
                        ->whereNull('team_id')
                        ->orWhereHas('club.users', fn ($q) => $q->where('users.id', $user->id))
                        ->orWhereHas('team.users', fn ($q) => $q->where('users.id', $user->id));
                })
                ->latest('id')
                ->limit(12)
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Post::class);

        $data = $request->validate([
            'club_id' => ['nullable', 'exists:clubs,id'],
            'team_id' => ['nullable', 'exists:teams,id'],
            'visibility' => ['required', Rule::in(Post::VISIBILITIES)],
            'content' => ['nullable', 'required_without_all:image,attachments', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,gif,pdf,doc,docx,xls,xlsx,txt,zip', 'max:10240'],
        ]);

        if (!empty($data['team_id'])) {
            $team = Team::findOrFail($data['team_id']);
            abort_unless($team->users()->where('users.id', auth()->id())->exists(), 403);
            $data['club_id'] = $team->club_id;
        }

        if (!empty($data['club_id']) && !auth()->user()->hasRole('super_admin')) {
            abort_unless(auth()->user()->clubs()->where('clubs.id', $data['club_id'])->exists(), 403);
        }

        abort_if($data['visibility'] === 'team' && empty($data['team_id']), 422, 'Team posts brauchen ein Team.');
        abort_if($data['visibility'] === 'organization' && empty($data['club_id']), 422, 'Organization posts brauchen eine Organization.');

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('posts', 'public');
        }

        $data['attachments'] = $request->file('attachments', []);

        $data['content'] ??= '';
        $data['club_id'] = $data['club_id'] ?? null;
        $data['team_id'] = $data['team_id'] ?? null;

        $this->service->create(auth()->user(), $data);

        return back()->with('success', 'Beitrag erstellt.');
    }

    public function update(Request $request, Post $post)
    {
        $this->authorize('update', $post);

        $data = $request->validate([
            'club_id' => ['nullable', 'exists:clubs,id'],
            'team_id' => ['nullable', 'exists:teams,id'],
            'visibility' => ['required', Rule::in(Post::VISIBILITIES)],
            'content' => ['nullable', 'required_without_all:image,attachments', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,gif,pdf,doc,docx,xls,xlsx,txt,zip', 'max:10240'],
        ]);

        if (!empty($data['team_id'])) {
            $team = Team::findOrFail($data['team_id']);
            abort_unless($team->users()->where('users.id', auth()->id())->exists(), 403);
            $data['club_id'] = $team->club_id;
        }

        abort_if($data['visibility'] === 'team' && empty($data['team_id']), 422, 'Team posts brauchen ein Team.');
        abort_if($data['visibility'] === 'organization' && empty($data['club_id']), 422, 'Organization posts brauchen eine Organization.');

        if ($request->hasFile('image')) {
            if ($post->image) {
                Storage::disk('public')->delete($post->image);
            }

            $data['image'] = $request->file('image')->store('posts', 'public');
        }

        $data['content'] ??= '';
        $data['club_id'] = $data['club_id'] ?? null;
        $data['team_id'] = $data['team_id'] ?? null;
        unset($data['attachments']);

        $post->update($data);
        $this->service->attachFiles($post, auth()->user(), $request->file('attachments', []));
        $this->service->recordActivity($post, 'post.updated', auth()->user());

        return back()->with('success', 'Beitrag aktualisiert.');
    }

    public function destroy(Post $post)
    {
        $this->authorize('delete', $post);

        $post->load('attachments.file');

        foreach ($post->attachments as $attachment) {
            if ($attachment->file) {
                Storage::disk('public')->delete($attachment->file->path);
                $attachment->file->delete();
            }
        }

        $post->delete();

        return back()->with('success', 'Beitrag gelöscht.');
    }
}
