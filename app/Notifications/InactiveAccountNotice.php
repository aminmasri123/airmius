<?php

namespace App\Notifications;

use App\Support\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InactiveAccountNotice extends Notification
{
    use Queueable;

    public function __construct(
        private string $stage,
        private ?string $scheduledDate = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $key = match ($this->stage) {
            'first' => 'inactive_account_first',
            'second' => 'inactive_account_second',
            default => 'inactive_account_scheduled',
        };

        return EmailTemplate::mail($key, [
            'name' => trim((string) ($notifiable->name ?? '')) ?: 'zusammen',
            'scheduled_date' => $this->scheduledDate ?: '-',
        ], route('login'));
    }
}
