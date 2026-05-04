<?php

namespace App\Notifications;

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
        return (new MailMessage)
            ->subject('Neue Anmeldung bei Airmius')
            ->greeting('Hallo,')
            ->line('in deinem Airmius-Konto gab es gerade eine erfolgreiche Anmeldung.')
            ->line('Zeitpunkt: '.$this->loggedInAt)
            ->line('IP-Adresse: '.($this->ipAddress ?: 'unbekannt'))
            ->line('Geraet/Browser: '.($this->userAgent ?: 'unbekannt'))
            ->line('Wenn du das warst, musst du nichts weiter tun.')
            ->line('Wenn du das nicht warst, aendere bitte sofort dein Passwort und informiere den Airmius-Support.');
    }
}
