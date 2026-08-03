<?php

namespace App\Services;

use App\Models\Follow;
use App\Models\User;
use App\Models\UserBlock;

class UserSocialProfileService
{
    public function state(User $profileUser, User $viewer): array
    {
        $isSelf = $viewer->is($profileUser);
        $hasBlocked = ! $isSelf && $viewer->hasBlocked($profileUser);
        $isBlocked = ! $isSelf && $profileUser->hasBlocked($viewer);

        return [
            'profile_user_id' => $profileUser->id,
            'is_following' => ! $isSelf && $profileUser->isFollowedBy($viewer),
            'can_follow' => ! $isSelf && $viewer->can('follow.user') && ! $hasBlocked && ! $isBlocked,
            'can_send_message' => ! $isSelf && ! $hasBlocked && ! $isBlocked
                && $profileUser->allowsDirectMessagesFrom($viewer),
            'has_blocked' => $hasBlocked,
            'is_blocked' => $isBlocked,
        ];
    }

    public function follow(User $profileUser, User $viewer): array
    {
        abort_if($viewer->is($profileUser), 422, 'Du kannst dir nicht selbst folgen.');
        abort_unless($viewer->can('follow.user'), 403);
        abort_if($viewer->hasBlocked($profileUser) || $profileUser->hasBlocked($viewer), 403, 'Diese Aktion ist nicht verfügbar.');

        Follow::firstOrCreate([
            'follower_id' => $viewer->id,
            'followed_id' => $profileUser->id,
        ]);

        return $this->state($profileUser, $viewer);
    }

    public function unfollow(User $profileUser, User $viewer): array
    {
        Follow::query()
            ->where('follower_id', $viewer->id)
            ->where('followed_id', $profileUser->id)
            ->delete();

        return $this->state($profileUser, $viewer);
    }

    public function block(User $profileUser, User $viewer): array
    {
        abort_if($viewer->is($profileUser), 422, 'Du kannst dich nicht selbst blockieren.');

        UserBlock::firstOrCreate([
            'user_id' => $viewer->id,
            'blocked_user_id' => $profileUser->id,
        ]);

        Follow::query()
            ->where(function ($query) use ($viewer, $profileUser) {
                $query->where('follower_id', $viewer->id)
                    ->where('followed_id', $profileUser->id);
            })
            ->orWhere(function ($query) use ($viewer, $profileUser) {
                $query->where('follower_id', $profileUser->id)
                    ->where('followed_id', $viewer->id);
            })
            ->delete();

        return $this->state($profileUser, $viewer);
    }

    public function unblock(User $profileUser, User $viewer): array
    {
        UserBlock::query()
            ->where('user_id', $viewer->id)
            ->where('blocked_user_id', $profileUser->id)
            ->delete();

        return $this->state($profileUser, $viewer);
    }
}
