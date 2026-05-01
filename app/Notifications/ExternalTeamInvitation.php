<?php

namespace App\Notifications;

use App\Models\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExternalTeamInvitation extends Notification
{
    use Queueable;

    public function __construct(private TeamInvitation $invitation) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $team = $this->invitation->team;
        $acceptUrl = route('auth.team-invitations.accept-by-token', $this->invitation->token);

        return (new MailMessage)
            ->subject('Einladung zu '.$team->name)
            ->markdown('emails.team-invitation', [
                'invitation' => $this->invitation,
                'acceptUrl' => $acceptUrl,
            ]);
    }
}
