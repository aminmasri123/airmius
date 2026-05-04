<?php

namespace App\Notifications;

use App\Support\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GuardianAccessCodeRequested extends Notification
{
    use Queueable;

    public function __construct(
        public string $code
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return EmailTemplate::mail('guardian_access_code', [
            'code' => $this->code,
        ]);
    }
}
