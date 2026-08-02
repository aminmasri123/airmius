<?php

namespace App\Notifications;

use App\Models\UserRoleApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrainerRegistrationReviewRequested extends Notification
{
    use Queueable;

    public function __construct(private UserRoleApplication $application) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $applicant = $this->application->user;

        $mail = (new MailMessage)
            ->subject('Neuer Trainerantrag: '.$applicant->name)
            ->line($applicant->name.' möchte den Trainerbereich auf Airmius nutzen.')
            ->line('Der Trainerzugang wurde sofort aktiviert und wartet auf die Prüfung durch Airmius.');

        if ($this->application->message) {
            $mail->line('Nachricht: '.$this->application->message);
        }

        return $mail->action('Traineranträge prüfen', route('admin.trainer-applications.index'));
    }
}
