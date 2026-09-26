<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy extends BasePolicy
{
    public function before($user, $ability)
    {
        return null;
    }

    public function viewAny(User $user)
    {
        return true;
    }

    public function view(User $user, Conversation $conversation)
    {
        return $conversation->users()->where('users.id', $user->id)->exists();
    }

    public function create(User $user)
    {
        return true;
    }
}
