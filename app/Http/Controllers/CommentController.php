<?php

namespace App\Http\Controllers;

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

    public function __construct(private ModerationService $moderation) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Post $post)
    {
        $this->authorize('view', $post);

        return response()->json([
            'comments' => $post->comments()
                ->where('moderation_status', 'approved')
                ->with('user:id,name,profile_photo_path')
                ->withCount('likes')
                ->latest('id')
                ->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Post $post)
    {
        $this->authorize('view', $post);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:1500'],
        ]);

        $comment = $post->comments()->create([
            'user_id' => auth()->id(),
            'content' => $data['content'],
        ]);
        $flag = $this->moderation->flagIfNeeded($comment, $comment->content, auth()->id());

        if ($flag) {
            return back()->with('success', 'Kommentar wurde zur Moderation eingereicht.');
        }

        if ($post->user_id !== auth()->id()) {
            AppNotification::send($post->user_id, 'post.comment', [
                'title' => auth()->user()->name.' hat deinen Beitrag kommentiert',
                'body' => str($comment->content)->limit(120)->toString(),
                'url' => route('auth.feed.index'),
                'actor_id' => auth()->id(),
                'actor_name' => auth()->user()->name,
                'post_id' => $post->id,
                'comment_id' => $comment->id,
            ]);
        }

        Activity::create([
            'user_id' => auth()->id(),
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

        return back()->with('success', 'Kommentar erstellt.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Comment $comment)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Comment $comment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Comment $comment)
    {
        $this->authorize('update', $comment);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:1500'],
        ]);

        $comment->forceFill([
            ...$data,
            'moderation_status' => 'approved',
        ])->save();

        $flag = $this->moderation->flagIfNeeded($comment, $comment->content, auth()->id());

        if ($flag) {
            return back()->with('success', 'Kommentar wurde zur Moderation eingereicht.');
        }

        return back()->with('success', 'Kommentar aktualisiert.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Comment $comment)
    {
        $this->authorize('delete', $comment);

        $comment->delete();

        return back()->with('success', 'Kommentar gelöscht.');
    }
}
