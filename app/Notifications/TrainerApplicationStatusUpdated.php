<?php

namespace App\Notifications;

use App\Models\UserRoleApplication;
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

        $message = (new MailMessage)
            ->subject($approved ? 'Dein Trainerantrag wurde freigegeben' : 'Dein Trainerantrag wurde abgelehnt')
            ->greeting('Hallo '.$notifiable->name.',');

        if ($approved) {
            $message->line('dein Trainerantrag wurde von Airmius geprüft und freigegeben. Deine Trainerfunktion bleibt aktiviert.');
        } else {
            $message
                ->line('dein Trainerantrag wurde von Airmius abgelehnt.')
                ->line('Die Trainerfunktion wurde wieder deaktiviert.');
        }

        if ($this->application->review_notes) {
            $message->line('Hinweis: '.$this->application->review_notes);
        }

        return $message->action($approved ? 'Zum Trainer-Cockpit' : 'Antrag ansehen', $actionUrl);
    }
}
