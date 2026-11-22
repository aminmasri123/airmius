<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\ClubDeletionAppChannel;
use App\Support\TransactionalMail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClubDeletionChanged extends Notification
{
    public function __construct(public string $title, public string $body, public string $url, public string $action, public string $event)
    {}

    public function via(object $notifiable): array
    {
        return $notifiable instanceof User
            ? [ClubDeletionAppChannel::class, 'mail']
            : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)->subject($this->title)->line($this->body)->action($this->action, $this->url);

        return app(TransactionalMail::class)->applyToMessage($message, 'security');
    }
}
