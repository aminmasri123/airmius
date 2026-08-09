<?php

namespace App\Notifications;

use App\Support\LocalizedMail;
use App\Support\TransactionalMail;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);
        $mail = LocalizedMail::for($notifiable);

        $message = (new MailMessage)
            ->subject($mail->text('verification.subject'))
            ->greeting($mail->greeting($notifiable))
            ->line($mail->text('verification.body'))
            ->action($mail->text('common.actions.verify_email'), $verificationUrl)
            ->line($mail->text('verification.security_note'))
            ->salutation($mail->text('common.salutation'));

        return app(TransactionalMail::class)->applyToMessage($message, 'security');
    }
}
