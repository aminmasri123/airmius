<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClubDeletionCodeRequested extends Notification
{
    public function __construct(public string $code, public string $clubName) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $german = $notifiable->language === 'de';

        return (new MailMessage)
            ->subject($german ? 'Vereinslöschung bestätigen' : 'Confirm club deletion')
            ->line($german
                ? "Für {$this->clubName} wurde ein Löschantrag vorbereitet."
                : "A deletion request was prepared for {$this->clubName}.")
            ->line($german
                ? "Dein Bestätigungscode lautet: {$this->code}. Er ist 15 Minuten gültig."
                : "Your confirmation code is: {$this->code}. It expires in 15 minutes.")
            ->line($german
                ? 'Wenn du das nicht warst, ignoriere diese E-Mail. Ohne Code wird keine Löschung beantragt.'
                : 'If this was not you, ignore this email. No deletion is requested without the code.');
    }
}
