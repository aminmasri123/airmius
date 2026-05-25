<?php

namespace App\Notifications;

use App\Support\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountSuspendedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private ?string $reason,
        private ?string $suspendedUntil,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return EmailTemplate::mail('account_suspended', [
            'name' => trim((string) ($notifiable->name ?? '')) ?: 'zusammen',
            'reason' => $this->reason ?: 'Regelverstoss oder wiederholte Moderationsverstoesse',
            'suspended_until' => $this->suspendedUntil ?: 'bis zur Prüfung durch das Airmius-Team',
        ], route('legal.reporting'));
    }
}
