<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Support\AppNotification;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    public function togglePost(Post $post)
    {
        $like = $post->likes()->where('user_id', auth()->id())->first();

        if ($like) {
            $like->delete();
        } else {
            $post->likes()->create([
                'user_id' => auth()->id()
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
