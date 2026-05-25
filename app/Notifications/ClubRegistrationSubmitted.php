<?php

namespace App\Notifications;

use App\Models\Club;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClubRegistrationSubmitted extends Notification
{
    use Queueable;

    public function __construct(private Club $club) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Dein Vereinsantrag wurde eingereicht')
            ->greeting('Hallo '.$notifiable->name.',')
            ->line('dein Verein "'.$this->club->name.'" wurde angelegt und wartet jetzt auf Prüfung.')
            ->line('Du bist sofort als Club-Owner hinterlegt und kannst den Verein im Dashboard verwalten.')
            ->line('öffentlich sichtbar und als offiziell markiert wird der Verein erst nach der Freigabe.')
            ->action('Verein ?ffnen', route('auth.clubs.show', $this->club->id));
    }
}
