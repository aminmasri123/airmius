<?php

namespace App\Notifications;

use App\Models\UserRoleApplication;
use App\Support\LocalizedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrainerApplicationStatusUpdated extends Notification
{
    use Queueable;

    public function __construct(private UserRoleApplication $application) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approved = $this->application->status === UserRoleApplication::STATUS_APPROVED;
        $actionUrl = $approved
            ? route('auth.trainer-cockpit.index')
            : route('auth.settings', ['tab' => 'roles']);
        $mail = LocalizedMail::for($notifiable);

        $message = (new MailMessage)
            ->subject($mail->text($approved ? 'trainer.approved_subject' : 'trainer.rejected_subject'))
            ->greeting($mail->greeting($notifiable));

        if ($approved) {
            $message->line($mail->text('trainer.approved_body'));
        } else {
            $message
                ->line($mail->text('trainer.rejected_body'))
                ->line($mail->text('trainer.disabled_body'));
        }

        if ($this->application->review_notes) {
            $message->line($mail->text('common.fields.note', ['value' => $this->application->review_notes]));
        }

        return $message->action($mail->text($approved ? 'common.actions.trainer_cockpit' : 'common.actions.view_application'), $actionUrl);
    }
}
