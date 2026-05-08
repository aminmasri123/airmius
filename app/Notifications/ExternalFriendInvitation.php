<?php

namespace App\Notifications;

use App\Models\FriendInvitation;
use App\Support\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExternalFriendInvitation extends Notification
{
    use Queueable;

    public function __construct(private FriendInvitation $invitation) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return EmailTemplate::mail('external_friend_invitation', [
            'sender_name' => $this->invitation->sender?->name ?: 'Jemand',
        ], route('auth.friends.invitations.accept-by-token', $this->invitation->token));
    }
}
