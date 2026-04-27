<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Post;
use App\Support\AppNotification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    use AuthorizesRequests;

    public function togglePost(Post $post)
    {
        $this->authorize('view', $post);

        $like = $post->likes()->where('user_id', auth()->id())->first();

        if ($like) {
            $like->delete();
        } else {
            $post->likes()->create([
                'user_id' => auth()->id()
            ]);

            Activity::create([
                'user_id' => auth()->id(),
                'club_id' => $post->club_id,
                'team_id' => $post->team_id,
                'type' => 'post.liked',
                'subject_type' => Post::class,
                'subject_id' => $post->id,
                'data' => [
                    'visibility' => $post->visibility,
                ],
            ]);

            if ($post->user_id !== auth()->id()) {
                AppNotification::send($post->user_id, 'post.like', [
                    'title' => auth()->user()->name.' gefällt dein Beitrag',
                    'body' => str($post->content)->limit(120)->toString(),
                    'url' => route('auth.feed.index'),
                    'actor_id' => auth()->id(),
                    'actor_name' => auth()->user()->name,
                    'post_id' => $post->id,
                ]);
            }
        }

        return back();
    }
}
