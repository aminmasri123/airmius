<?php

namespace App\Notifications;

use App\Models\UserRoleApplication;
use App\Support\LocalizedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrainerApplicationStatusUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    private string $decision;

    private ?string $reviewNotes;

    public function __construct(UserRoleApplication $application)
    {
        // Preserve the decision even if the application changes before delivery.
        $this->decision = $application->status;
        $this->reviewNotes = $application->review_notes;
        $connection = config('queue.default');
        $this->onConnection(in_array($connection, ['database', 'redis', 'sqs', 'beanstalkd'], true)
            ? $connection : 'database');
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approved = $this->decision === UserRoleApplication::STATUS_APPROVED;
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

        if ($this->reviewNotes) {
            $message->line($mail->text('common.fields.note', ['value' => $this->reviewNotes]));
        }

        return $message->action($mail->text($approved ? 'common.actions.trainer_cockpit' : 'common.actions.view_application'), $actionUrl);
    }
}
