<?php

namespace App\Notifications;

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
        $message = (new MailMessage)
            ->greeting('Hallo '.(trim((string) ($notifiable->name ?? '')) ?: 'zusammen').',');

        return match ($this->stage) {
            'first' => $message
                ->subject('Dein Airmius Konto war lange nicht aktiv')
                ->line('dein Airmius Konto wurde seit laengerer Zeit nicht genutzt.')
                ->line('Aus Datenschutzgruenden pruefen wir inaktive Konten regelmaessig.')
                ->line('Wenn du Airmius weiter nutzen moechtest, melde dich einfach wieder an. Dadurch bleibt dein Konto aktiv.')
                ->action('Bei Airmius anmelden', route('login')),
            'second' => $message
                ->subject('Erinnerung: Dein Airmius Konto ist weiterhin inaktiv')
                ->line('dein Airmius Konto ist weiterhin inaktiv.')
                ->line('Wenn du dich wieder anmeldest, wird die geplante Datenschutzpruefung zurueckgesetzt.')
                ->line('Ohne Reaktion kann dein Konto spaeter deaktiviert und anonymisiert werden.')
                ->action('Konto aktiv halten', route('login')),
            default => $message
                ->subject('Airmius Konto wird zur Anonymisierung vorgemerkt')
                ->line('dein Airmius Konto ist seit laengerer Zeit inaktiv.')
                ->line('Wir haben dein Konto deshalb zur Datenschutz-Anonymisierung vorgemerkt.')
                ->line('Geplantes Datum: '.($this->scheduledDate ?: '-'))
                ->line('Wenn du dich vor diesem Datum wieder anmeldest, bleibt dein Konto aktiv.')
                ->action('Konto aktiv halten', route('login')),
        };
    }
}
