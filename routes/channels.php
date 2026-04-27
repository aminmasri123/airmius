<?php

use App\Models\Conversation;
use App\Models\Club;
use App\Models\Team;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('chat.conversation.{conversation}', function ($user, Conversation $conversation) {
    return $conversation->users()->where('users.id', $user->id)->exists();
});

Broadcast::channel('notifications.user.{userId}', function ($user, int $userId) {
    return (int) $user->id === $userId;
});

Broadcast::channel('events.team.{team}', function ($user, Team $team) {
    return $team->users()->where('users.id', $user->id)->exists()
        || $user->can('event.update')
        || $user->can('event.create');
});

Broadcast::channel('events.club.{club}', function ($user, Club $club) {
    return $club->users()->where('users.id', $user->id)->exists()
        || $user->can('event.update')
        || $user->can('event.create');
});

Broadcast::channel('users.status', function ($user) {
    return ['id' => $user->id, 'name' => $user->name, 'status' => $user->status];
});
