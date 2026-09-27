<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\ClubDeletionChanged;
use App\Support\AppNotification;

class ClubDeletionAppChannel
{
    public function send(User $notifiable, ClubDeletionChanged $notification): void
    {
        AppNotification::send($notifiable, 'club.deletion.'.$notification->event, [
            'title' => $notification->title,
            'body' => $notification->body,
            'url' => $notification->url,
        ], ['priority' => 'high', 'dedupe_key' => $notification->id]);
    }
}
