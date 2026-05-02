<?php

namespace App\Notifications;

use App\Models\ClubExternalMember;
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
        $acceptUrl = route('auth.club-member-invitations.accept', $this->externalMember->invitation_token);

        return (new MailMessage)
            ->subject('Einladung zu '.$club->name.' auf Airmius')
            ->greeting('Hallo'.($this->externalMember->name ? ' '.$this->externalMember->name : '').',')
            ->line($club->name.' hat dich als Mitglied hinterlegt und moechte dich mit Airmius verknuepfen.')
            ->line('Mit einem Airmius-Konto kannst du deine Vereinsdaten, Rechnungen, Zahlungshistorie, Teams und Nachrichten besser ueberblicken.')
            ->action('Einladung ansehen', $acceptUrl)
            ->line('Wenn du bereits ein Konto mit dieser E-Mail hast, kannst du dich anmelden und die Verknuepfung abschliessen. Falls nicht, kannst du dich mit dieser E-Mail registrieren.');
    }
}
