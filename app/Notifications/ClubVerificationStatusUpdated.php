<?php

namespace App\Notifications;

use App\Models\Club;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClubVerificationStatusUpdated extends Notification
{
    use Queueable;

    public function __construct(private Club $club) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approved = $this->club->verification_status === 'verified';

        $message = (new MailMessage)
            ->subject($approved ? 'Dein Verein wurde freigegeben' : 'Dein Vereinsantrag wurde abgelehnt')
            ->greeting('Hallo '.$notifiable->name.',');

        if ($approved) {
            $message
                ->line('dein Verein "'.$this->club->name.'" wurde geprueft und freigegeben.')
                ->line($this->club->is_official ? 'Der Verein ist jetzt oeffentlich sichtbar und als offiziell markiert.' : 'Der Verein ist jetzt oeffentlich sichtbar.');
        } else {
            $message
                ->line('dein Vereinsantrag für "'.$this->club->name.'" wurde abgelehnt.')
                ->line('Bitte pruefe die Hinweise im Dashboard oder kontaktiere den Support.');
        }

        if ($this->club->verification_notes) {
            $message->line('Hinweis: '.$this->club->verification_notes);
        }

        return $message->action('Verein oeffnen', route('auth.clubs.show', $this->club->id));
    }
}
