<?php

namespace App\Notifications;

use App\Support\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserDataErasureCompleted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $recipientLocale = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return EmailTemplate::mail('data_erasure_completed', [
            'name' => $this->name ?: __('data_erasure.email_templates.together', locale: $this->recipientLocale),
        ], locale: $this->recipientLocale);
    }
}
