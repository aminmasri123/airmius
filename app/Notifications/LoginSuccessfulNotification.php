<?php

namespace App\Notifications;

use App\Support\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginSuccessfulNotification extends Notification
{
    use Queueable;

    public function __construct(
        public ?string $ipAddress,
        public ?string $userAgent,
        public string $loggedInAt
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return EmailTemplate::mail('login_successful', [
            'logged_in_at' => $this->loggedInAt,
            'ip_address' => $this->ipAddress ?: 'unbekannt',
            'user_agent' => $this->userAgent ?: 'unbekannt',
        ]);
    }
}
