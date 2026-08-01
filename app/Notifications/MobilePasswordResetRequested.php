<?php

namespace App\Notifications;

use App\Support\EmailTemplate;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class MobilePasswordResetRequested extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $baseUrl = rtrim((string) config('airmius.mobile_app_url', 'https://app.airmius.com'), '/');
        // Keep the query-string shape for compatibility with already installed
        // app versions. The web application also exposes this URL shape.
        $url = $baseUrl.'/reset-password?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return EmailTemplate::mail('password_reset', [
            'reset_url' => $url,
            'expires_minutes' => config('auth.passwords.users.expire', 60),
        ], $url)->salutation('Beste Grüße, dein Airmius Team');
    }
}
