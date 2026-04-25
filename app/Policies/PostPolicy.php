<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PostPolicy extends BasePolicy
{
    public function viewAny(User $user)
    {
        return !$user->hasRole('guest');
    }

    public function view(User $user, Post $post)
    {
        return $this->inClub($user, $post->club);
    }

    public function create(User $user)
    {
        return !$user->hasRole('guest');
    }

    public function update(User $user, Post $post)
    {
        return $post->user_id === $user->id
            || $this->hasRole($user, ['media_manager','club_admin']);
    }

    public function delete(User $user, Post $post)
    {
        return $post->user_id === $user->id
            || $this->isClubAdmin($user)
            || $this->isSystem($user);
    }
}
