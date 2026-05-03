<?php

namespace App\Notifications;

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
        return (new MailMessage)
            ->subject('Dein Eltern-Zugangscode für Airmius')
            ->greeting('Hallo,')
            ->line('du hast einen Zugangscode für den Elternbereich von Airmius angefordert.')
            ->line('Dein Code lautet: '.$this->code)
            ->line('Der Code ist 15 Minuten gueltig.')
            ->line('Wenn du diesen Code nicht angefordert hast, kannst du diese E-Mail ignorieren.');
    }
}
