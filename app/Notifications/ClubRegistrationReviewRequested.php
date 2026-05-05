<?php

namespace App\Notifications;

use App\Models\Club;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClubRegistrationReviewRequested extends Notification
{
    use Queueable;

    public function __construct(
        private Club $club,
        private User $applicant,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Neuer Vereinsantrag: '.$this->club->name)
            ->line($this->applicant->name.' hat einen Verein registriert.')
            ->line('Verein: '.$this->club->name)
            ->line('Land: '.$this->club->country)
            ->line('Beantragte Vereinsnummer: '.($this->club->requested_official_club_number ?: '-'))
            ->action('Antrag pruefen', route('admin.club-verifications.index'));
    }
}
