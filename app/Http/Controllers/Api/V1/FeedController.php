<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PostResource;
use App\Models\Club;
use App\Models\Post;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeedController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $clubIds = $user->clubs()->pluck('clubs.id')->all();
        $teamIds = $user->teams()->pluck('teams.id')->all();

        $posts = Post::query()
            ->with(['user', 'club', 'team', 'sport'])
            ->withCount(['comments', 'likes', 'helpfuls'])
            ->where(function ($query) use ($user, $clubIds, $teamIds) {
                $query
                    ->where('visibility', 'public')
                    ->orWhere('user_id', $user->id)
                    ->orWhereIn('club_id', $clubIds)
                    ->orWhereIn('team_id', $teamIds);
            })
            ->where(function ($query) use ($user) {
                $query
                    ->where('moderation_status', 'approved')
                    ->orWhere('user_id', $user->id);
            })
            ->latest()
            ->paginate($this->perPage($request));

        return PostResource::collection($posts);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
            'visibility' => ['required', Rule::in(Post::VISIBILITIES)],
            'post_type' => ['nullable', Rule::in(Post::TYPES)],
            'club_id' => ['nullable', 'integer', 'exists:clubs,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'sport_id' => ['nullable', 'integer', 'exists:sports,id'],
            'image' => ['nullable', 'string', 'max:2048'],
        ]);

        $user = $request->user();
        $clubId = $data['club_id'] ?? null;
        $teamId = $data['team_id'] ?? null;

        if ($teamId) {
            $team = Team::query()->with('club')->findOrFail($teamId);
            abort_unless($team->users()->where('users.id', $user->id)->exists(), 403);

            $clubId = $team->club_id;
        }

        if ($clubId) {
            $club = Club::query()->findOrFail($clubId);
            abort_unless($club->users()->where('users.id', $user->id)->exists(), 403);
        }

        if ($data['visibility'] === 'team') {
            abort_unless((bool) $teamId, 422, 'Team posts require a team_id.');
        }

        if ($data['visibility'] === 'organization') {
            abort_unless((bool) $clubId, 422, 'Organization posts require a club_id or team_id.');
        }

        $post = Post::create([
            'user_id' => $user->id,
            'club_id' => $clubId,
            'team_id' => $teamId,
            'sport_id' => $data['sport_id'] ?? null,
            'post_type' => $data['post_type'] ?? 'normal',
            'content_origin' => 'self',
            'content' => $data['content'],
            'image' => $data['image'] ?? null,
            'visibility' => $data['visibility'],
        ]);

        return (new PostResource(
            $post->load(['user', 'club', 'team', 'sport'])->loadCount(['comments', 'likes', 'helpfuls'])
        ))->response()->setStatusCode(201);
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->integer('per_page', 20), 1), 50);
    }
}
