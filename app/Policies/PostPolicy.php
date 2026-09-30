<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PostPolicy extends BasePolicy
{
    public function before($user, $ability, ...$arguments)
    {
        $post = $arguments[0] ?? null;
        if ($post instanceof Post && $post->visibility === 'private') {
            return $post->user_id === $user->id ? null : false;
        }

        return parent::before($user, $ability);
    }

    public function viewAny(User $user)
    {
        return !$user->hasRole('guest');
    }

    public function view(User $user, Post $post)
    {
        if ($post->moderation_status === 'removed') {
            return false;
        }

        if ($post->moderation_status !== 'approved' && $post->user_id !== $user->id) {
            return false;
        }

        if ($post->user_id === $user->id) {
            return true;
        }

        if ($post->visibility === 'private') {
            return false;
        }

        if ($post->visibility === 'friends') {
            return $user->isFriendsWith($post->user);
        }

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
        return $post->user_id === $user->id;
    }

    public function delete(User $user, Post $post)
    {
        return $post->user_id === $user->id;
    }
}
