<?php

namespace App\Notifications;

use App\Support\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountDeletionCompleted extends Notification
{
    use Queueable;

    public function __construct(
        public ?string $name = null
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return EmailTemplate::mail('account_deletion_completed', [
            'name' => $this->name ?: 'zusammen',
        ]);
    }
}
