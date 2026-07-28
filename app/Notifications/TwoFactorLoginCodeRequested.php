<?php

namespace App\Notifications;

use App\Support\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TwoFactorLoginCodeRequested extends Notification
{
    use Queueable;

    public function __construct(
        public string $code
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return EmailTemplate::mail('login_two_factor_code', [
            'name' => $notifiable->name,
            'code' => $this->code,
            'expires_minutes' => 10,
        ]);
    }
}
