<?php

namespace App\Notifications;

use App\Models\TeamInvitation;
use App\Support\EmailTemplate;
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

        return EmailTemplate::mail('external_team_invitation', [
            'team_name' => $team->name,
        ], route('auth.team-invitations.accept-by-token', $this->invitation->token));
    }
}
