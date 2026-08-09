<?php

namespace App\Notifications;

use App\Support\EmailTemplate;
use App\Support\LocalizedMail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class MobilePasswordResetRequested extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        // Use the same canonical web URL as the website reset email. The
        // app.airmius.com host does not serve the web reset page, while the
        // main application host does.
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $mail = LocalizedMail::for($notifiable);

        return EmailTemplate::mail('password_reset', [
            'reset_url' => $url,
            'expires_minutes' => config('auth.passwords.users.expire', 60),
        ], $url)->salutation($mail->text('common.salutation'));
    }
}
