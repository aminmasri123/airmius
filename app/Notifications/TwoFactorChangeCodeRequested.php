<?php

namespace App\Notifications;

use App\Support\TransactionalMail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TwoFactorChangeCodeRequested extends Notification
{
    public function __construct(public string $code, public string $purpose) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $action = match ($this->purpose) {
            'enable' => 'Zwei-Faktor-Authentifizierung einrichten',
            'disable' => 'Zwei-Faktor-Authentifizierung ausschalten',
            'recovery' => 'neue Wiederherstellungscodes erstellen',
        };

        $message = (new MailMessage)->subject('Airmius: Sicherheitsänderung bestätigen')
            ->greeting('Hallo '.$notifiable->name.',')
            ->line('Du möchtest folgende Aktion bestätigen: '.$action.'.')
            ->line('Dein Bestätigungscode: '.$this->code)
            ->line('Der Code gilt einmalig für diese Aktion und läuft nach 10 Minuten ab.')
            ->line('Gib den Code nur in Airmius ein. Wenn du diese Änderung nicht angefordert hast, gib den Code nicht weiter und prüfe deine Kontosicherheit.');

        return app(TransactionalMail::class)->applyToMessage($message, 'security');
    }
}
