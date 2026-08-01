<?php

namespace App\Notifications;

use App\Support\TransactionalMail;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

class MobileVerifyEmail extends VerifyEmail
{
    protected function verificationUrl($notifiable): string
    {
        $relativeUrl = URL::temporarySignedRoute(
            'email.verification.bridge',
            now()->addMinutes((int) config('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
            absolute: false
        );

        return rtrim((string) config('airmius.verification_url'), '/').$relativeUrl;
    }

    public function toMail($notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);

        $message = (new MailMessage)
            ->subject('E-Mail-Adresse für Airmius bestätigen')
            ->greeting('Hallo '.$notifiable->name.',')
            ->line('Bestätige deine E-Mail-Adresse, um dein Airmius-Konto vollständig zu aktivieren.')
            ->action('E-Mail-Adresse bestätigen', $url)
            ->line('Der Sicherheitslink ist zeitlich begrenzt. Falls du das Konto nicht erstellt hast, kannst du diese Nachricht ignorieren.')
            ->salutation('Beste Grüße, dein Airmius Team');

        return app(TransactionalMail::class)->applyToMessage($message, 'security');
    }
}
