<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ConversationPolicy extends BasePolicy
{
    public function viewAny(User $user)
    {
        return true;
    }

    public function view(User $user, Conversation $conversation)
    {
        return $conversation->users->contains($user->id)
            || $this->isCoach($user)
            || $this->isClubAdmin($user);
    }

    public function create(User $user)
    {
        return true;
    }
}
