<?php

namespace App\Notifications;

use App\Models\Club;
use App\Support\LocalizedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClubRegistrationSubmitted extends Notification
{
    use Queueable;

    public function __construct(private Club $club) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = LocalizedMail::for($notifiable);

        return (new MailMessage)
            ->subject($mail->text('club_registration.submitted_subject'))
            ->greeting($mail->greeting($notifiable))
            ->line($mail->text('club_registration.submitted_body', ['club' => $this->club->name]))
            ->line($mail->text('club_registration.owner_body'))
            ->line($mail->text('club_registration.visibility_body'))
            ->action($mail->text('common.actions.open_club'), route('auth.clubs.show', $this->club->id));
    }
}
