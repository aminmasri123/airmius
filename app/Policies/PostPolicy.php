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
        if ($post->visibility === 'public') {
            return true;
        }

        if ($post->visibility === 'team' && $post->team_id) {
            return $post->team->users()->where('users.id', $user->id)->exists();
        }

        return $post->club && $this->inClub($user, $post->club);
    }

    public function create(User $user)
    {
        return $user->can('post.create')
            || !$user->hasRole('guest');
    }

    public function update(User $user, Post $post)
    {
        return $post->user_id === $user->id
            || ($post->club && $user->can('post.update') && $this->inClub($user, $post->club))
            || ($post->team && $user->can('post.update') && $post->team->users()->where('users.id', $user->id)->exists())
            || $this->hasRole($user, ['media_manager','club_admin']);
    }

    public function delete(User $user, Post $post)
    {
        return $post->user_id === $user->id
            || ($post->club && $user->can('post.delete') && $this->inClub($user, $post->club))
            || ($post->team && $user->can('post.delete') && $post->team->users()->where('users.id', $user->id)->exists())
            || $this->isClubAdmin($user)
            || $this->isSystem($user);
    }
}
