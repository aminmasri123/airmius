<?php

namespace App\Notifications;

use App\Models\ClubExternalMember;
use App\Support\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExternalClubMembershipInvitation extends Notification
{
    use Queueable;

    public function __construct(private ClubExternalMember $externalMember) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $club = $this->externalMember->club;

        return EmailTemplate::mail('external_club_membership_invitation', [
            'name' => $this->externalMember->name ?: 'zusammen',
            'club_name' => $club->name,
            'inviter_name' => $this->externalMember->creator?->name ?: $club->name,
        ], route('auth.club-member-invitations.accept', $this->externalMember->invitation_token));
    }
}
