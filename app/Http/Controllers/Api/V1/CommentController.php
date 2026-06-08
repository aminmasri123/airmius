<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CommentResource;
use App\Models\Activity;
use App\Models\Comment;
use App\Models\Post;
use App\Services\ModerationService;
use App\Support\AppNotification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly ModerationService $moderation) {}

    public function index(Request $request, Post $post)
    {
        $this->authorize('view', $post);

        $comments = $post->comments()
            ->where(function ($query) use ($request) {
                $query->where('moderation_status', 'approved')
                    ->orWhere('user_id', $request->user()->id);
            })
            ->with(['user', 'post:id,user_id'])
            ->withCount('likes')
            ->latest('id')
            ->paginate($this->perPage($request));

        return CommentResource::collection($comments);
    }

    public function store(Request $request, Post $post)
    {
        $this->authorize('view', $post);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:1500'],
        ]);

        $comment = $post->comments()->create([
            'user_id' => $request->user()->id,
            'content' => $data['content'],
        ]);

        $this->moderation->flagIfNeeded($comment, $comment->content, $request->user()->id);

        if ($post->user_id !== $request->user()->id) {
            AppNotification::send($post->user_id, 'post.comment', [
                'title' => $request->user()->name.' hat deinen Beitrag kommentiert',
                'body' => str($comment->content)->limit(120)->toString(),
                'url' => '/feed',
                'actor_id' => $request->user()->id,
                'actor_name' => $request->user()->name,
                'post_id' => $post->id,
                'comment_id' => $comment->id,
            ]);
        }

        Activity::create([
            'user_id' => $request->user()->id,
            'club_id' => $post->club_id,
            'team_id' => $post->team_id,
            'type' => 'post.commented',
            'subject_type' => Post::class,
            'subject_id' => $post->id,
            'data' => [
                'comment_id' => $comment->id,
                'visibility' => $post->visibility,
            ],
        ]);

        return (new CommentResource($comment->load(['user', 'post:id,user_id'])->loadCount('likes')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Comment $comment)
    {
        abort_unless($comment->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:1500'],
        ]);

        $comment->forceFill([
            'content' => $data['content'],
            'moderation_status' => 'approved',
        ])->save();

        $this->moderation->flagIfNeeded($comment, $comment->content, $request->user()->id);

        return new CommentResource($comment->fresh()->load(['user', 'post:id,user_id'])->loadCount('likes'));
    }

    public function destroy(Request $request, Comment $comment)
    {
        abort_unless($comment->user_id === $request->user()->id || $comment->post->user_id === $request->user()->id, 403);

        $comment->delete();


        return response()->noContent();

    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->integer('per_page', 20), 1), 50);
    }
}

