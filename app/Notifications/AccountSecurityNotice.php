<?php

namespace App\Notifications;

use App\Support\TransactionalMail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountSecurityNotice extends Notification implements \Illuminate\Contracts\Queue\ShouldQueue
{
    use \Illuminate\Bus\Queueable;

    public function __construct(public string $message, public ?string $verificationUrl = null)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject('Airmius – Kontosicherheit')->line($this->message);
        if ($this->verificationUrl) {
            $mail->action('Neue E-Mail-Adresse bestätigen', $this->verificationUrl);
        } else {
            $mail->line('Falls dir diese Änderung unbekannt ist, öffne Airmius und prüfe deine aktiven Sitzungen oder kontaktiere den Support.');
        }

        return app(TransactionalMail::class)->applyToMessage($mail, 'security');
    }
}
