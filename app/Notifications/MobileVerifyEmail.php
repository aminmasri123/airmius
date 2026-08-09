<?php

namespace App\Notifications;

use App\Support\LocalizedMail;
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
        $mail = LocalizedMail::for($notifiable);

        $message = (new MailMessage)
            ->subject($mail->text('verification.subject'))
            ->greeting($mail->greeting($notifiable))
            ->line($mail->text('verification.body'))
            ->action($mail->text('common.actions.verify_email'), $url)
            ->line($mail->text('verification.security_note'))
            ->salutation($mail->text('common.salutation'));

        return app(TransactionalMail::class)->applyToMessage($message, 'security');
    }
}
