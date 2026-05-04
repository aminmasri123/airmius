<?php

namespace App\Notifications;

use App\Support\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginLockoutNotification extends Notification
{
    use Queueable;

    public function __construct(
        public ?string $ipAddress,
        public ?string $userAgent,
        public string $lockedAt
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return EmailTemplate::mail('login_lockout', [
            'locked_at' => $this->lockedAt,
            'ip_address' => $this->ipAddress ?: 'unbekannt',
            'user_agent' => $this->userAgent ?: 'unbekannt',
        ]);
    }
}
