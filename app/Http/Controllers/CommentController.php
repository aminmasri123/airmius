<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Post;
use App\Support\AppNotification;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
        $data = $request->validate([
            'content' => ['required', 'string', 'max:1500'],
        ]);

        $comment = $post->comments()->create([
            'user_id' => auth()->id(),
            'content' => $data['content'],
        ]);

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
        abort_unless($comment->user_id === auth()->id(), 403);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:1500'],
        ]);

        $comment->update($data);

        return back()->with('success', 'Kommentar aktualisiert.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Comment $comment)
    {
        abort_unless($comment->user_id === auth()->id() || $comment->post->user_id === auth()->id(), 403);

        $comment->delete();

        return back()->with('success', 'Kommentar gelöscht.');
    }
}
