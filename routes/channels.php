<?php

use App\Models\Club;
use App\Models\Conversation;
use App\Models\Team;
use App\Support\ClubPermissions;
use App\Support\ClubActiveMembership;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('chat.conversation.{conversation}', function ($user, Conversation $conversation) {
    return $conversation->users()->where('users.id', $user->id)->exists();
});

Broadcast::channel('chat.user.{userId}', function ($user, int $userId) {
    return (int) $user->id === $userId;
});

Broadcast::channel('notifications.user.{userId}', function ($user, int $userId) {
    return (int) $user->id === $userId;
});

Broadcast::channel('events.team.{team}', function ($user, Team $team) {
    return ClubActiveMembership::allows($team->club, $user)
        && ($team->users()->where('users.id', $user->id)->exists()
            || ClubPermissions::allowsForTeam($team, $user, ClubPermissions::EVENTS_EDIT));
});

Broadcast::channel('events.club.{club}', function ($user, Club $club) {
    return ClubActiveMembership::allows($club, $user)
        && ($club->users()->where('users.id', $user->id)->exists()
            || ClubPermissions::allows($club, $user, ClubPermissions::EVENTS_EDIT));
});

Broadcast::channel('users.status.{userId}', function ($user, int $userId) {
    return (int) $user->id === $userId;
});
