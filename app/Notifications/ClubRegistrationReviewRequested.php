<?php

namespace App\Notifications;

use App\Models\Club;
use App\Models\User;
use App\Support\LocalizedMail;
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
        $mail = LocalizedMail::for($notifiable);

        return (new MailMessage)
            ->subject($mail->text('club_registration.review_subject', ['club' => $this->club->name]))
            ->line($mail->text('club_registration.review_body', ['applicant' => $this->applicant->name]))
            ->line($mail->text('common.fields.club', ['value' => $this->club->name]))
            ->line($mail->text('common.fields.country', ['value' => $this->club->country]))
            ->line($mail->text('common.fields.requested_club_number', ['value' => $this->club->requested_official_club_number ?: $mail->text('common.not_set')]))
            ->action($mail->text('common.actions.review_club'), route('admin.club-verifications.index'));
    }
}
