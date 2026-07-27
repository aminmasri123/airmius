<?php

namespace App\Notifications;

use App\Support\TransactionalMail;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        $message = (new MailMessage)
            ->subject('E-Mail-Adresse für Airmius bestätigen')
            ->greeting('Hallo '.$notifiable->name.',')
            ->line('Bestätige deine E-Mail-Adresse, um dein Airmius-Konto vollständig zu aktivieren.')
            ->action('E-Mail-Adresse bestätigen', $verificationUrl)
            ->line('Der Sicherheitslink ist zeitlich begrenzt. Falls du das Konto nicht erstellt hast, kannst du diese Nachricht ignorieren.')
            ->salutation('Beste Grüße, dein Airmius Team');

        return app(TransactionalMail::class)->applyToMessage($message, 'security');
    }
}
