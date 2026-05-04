<?php

namespace App\Notifications;

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
        return (new MailMessage)
            ->subject('Dein Airmius-Konto wurde geloescht')
            ->greeting('Hallo'.($this->name ? ' '.$this->name : '').',')
            ->line('dein Airmius-Konto wurde erfolgreich geloescht.')
            ->line('Diese E-Mail bestaetigt, dass die Kontoloeschung abgeschlossen wurde.')
            ->line('Falls du diese Loeschung nicht selbst ausgeloest hast, kontaktiere bitte den Airmius-Support.');
    }
}
