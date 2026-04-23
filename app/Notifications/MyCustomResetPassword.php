<?php
namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class MyCustomResetPassword  extends ResetPassword
{
    public function toMail($notifiable)
    {
        // Hier baust du die URL für den Button
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject(__('Passwort zurücksetzen'))
            ->greeting(__('Hallo!'))
            ->line(__('Du erhältst diese E-Mail, weil wir eine Anfrage zum Zurücksetzen des Passworts für dein Konto erhalten haben.'))
            ->action(__('Passwort zurücksetzen'), $url)
            ->line(__('Dieser Link läuft in 60 Minuten ab.'))
            ->salutation(__('Beste Grüße, dein Airmius Team'));

    }
}

