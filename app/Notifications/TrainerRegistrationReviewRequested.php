<?php

namespace App\Notifications;

use App\Models\UserRoleApplication;
use App\Support\LocalizedMail;
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
        $localized = LocalizedMail::for($notifiable);

        $mail = (new MailMessage)
            ->subject($localized->text('trainer.review_subject', ['applicant' => $applicant->name]))
            ->line($localized->text('trainer.review_body', ['applicant' => $applicant->name]))
            ->line($localized->text('trainer.review_pending'));

        if ($this->application->message) {
            $mail->line($localized->text('common.fields.message', ['value' => $this->application->message]));
        }

        return $mail->action($localized->text('common.actions.review_trainers'), route('admin.trainer-applications.index'));
    }
}
