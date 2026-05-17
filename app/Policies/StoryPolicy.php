<?php

namespace App\Policies;

use App\Models\Story;
use App\Models\User;

class StoryPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->hasRole('guest');
    }

    public function view(User $user, Story $story): bool
    {
        if ($story->moderation_status === 'removed' || $story->expires_at?->isPast()) {
            return false;
        }

        if ($story->moderation_status !== 'approved' && $story->user_id !== $user->id) {
            return false;
        }

        if ($story->user_id === $user->id) {
            return true;
        }

        if ($story->visibility === 'public') {
            return true;
        }

        if ($story->visibility === 'team' && $story->team_id) {
            return $story->team->users()->where('users.id', $user->id)->exists();
        }

        return $story->club && $this->inClub($user, $story->club);
    }

    public function create(User $user): bool
    {
        return $user->can('post.create') || ! $user->hasRole('guest');
    }

    public function delete(User $user, Story $story): bool
    {
        return $story->user_id === $user->id;
    }
}
