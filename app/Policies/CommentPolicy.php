<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

class CommentPolicy extends BasePolicy
{
    public function before($user, $ability)
    {
        return null;
    }

    public function viewAny(User $user)
    {
        return true;
    }

    public function create(User $user)
    {
        return ! $user->hasRole('guest');
    }

    public function update(User $user, Comment $comment)
    {
        return $comment->user_id === $user->id;
    }

    public function delete(User $user, Comment $comment)
    {
        return $comment->user_id === $user->id
            || $comment->post?->user_id === $user->id;
    }
}
