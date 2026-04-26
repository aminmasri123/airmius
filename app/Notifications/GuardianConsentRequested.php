<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GuardianConsentRequested extends Notification
{
    use Queueable;

    public function __construct(
        public User $minor
    ) {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Zustimmung zur Registrierung bei Airmius')
            ->greeting('Hallo,')
            ->line($this->minor->name.' hat sich bei Airmius registriert und ist unter 16 Jahre alt.')
            ->line('Bitte bestaetigen Sie die Registrierung nur, wenn Sie erziehungsberechtigt sind.')
            ->action('Registrierung bestaetigen', route('guardian-consent.show', $this->minor->guardian_consent_token))
            ->line('Wenn Sie diese Anfrage nicht erwartet haben, koennen Sie diese E-Mail ignorieren.');
    }
}
