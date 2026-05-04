<?php

namespace App\Notifications;

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
        return (new MailMessage)
            ->subject('Mehrere fehlgeschlagene Anmeldeversuche bei Airmius')
            ->greeting('Hallo,')
            ->line('fuer dein Airmius-Konto wurden mehrere falsche Login-Versuche erkannt.')
            ->line('Der Login wurde voruebergehend blockiert, um dein Konto zu schuetzen.')
            ->line('Zeitpunkt: '.$this->lockedAt)
            ->line('IP-Adresse: '.($this->ipAddress ?: 'unbekannt'))
            ->line('Geraet/Browser: '.($this->userAgent ?: 'unbekannt'))
            ->line('Wenn du das warst, warte bitte kurz und versuche es danach erneut.')
            ->line('Wenn du das nicht warst, aendere bitte dein Passwort und pruefe deine Kontosicherheit.');
    }
}
