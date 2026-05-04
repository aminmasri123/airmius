<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountDeletionCodeRequested extends Notification
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
        return (new MailMessage)
            ->subject('Bestaetigungscode zur Kontoloeschung')
            ->greeting('Hallo,')
            ->line('du hast angefordert, dein Airmius-Konto zu loeschen.')
            ->line('Dein Bestaetigungscode lautet: '.$this->code)
            ->line('Der Code ist 15 Minuten gueltig.')
            ->line('Wenn du dein Konto nicht loeschen moechtest, kannst du diese E-Mail ignorieren.');
    }
}
