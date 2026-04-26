<?php

namespace App\Services;

use App\Models\Post;

class PostService
{
    public function create($user, $data)
    {
        return Post::create([
            'user_id' => $user->id,
            'club_id' => $data['club_id'] ?? null,
            'content' => $data['content'],
            'image' => $data['image'] ?? null,
        ]);
    }
}
