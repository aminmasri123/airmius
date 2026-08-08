<?php

namespace App\Notifications;

use App\Models\Club;
use App\Support\SupportedLocale;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

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
        $locale = SupportedLocale::normalize($notifiable->language ?? null) ?? SupportedLocale::DEFAULT;
        $copy = fn (string $key, array $replace = []): string => Lang::get(
            'platform.organization.verification_mail.'.$key,
            $replace,
            $locale,
        );

        $message = (new MailMessage)
            ->subject($copy($approved ? 'approved_subject' : 'rejected_subject'))
            ->greeting($copy('greeting', ['name' => $notifiable->name]));

        if ($approved) {
            $message
                ->line($copy('approved_line', ['club' => $this->club->name]))
                ->line($copy($this->club->is_official ? 'approved_official' : 'approved_public'));
        } else {
            $message
                ->line($copy('rejected_line', ['club' => $this->club->name]))
                ->line($copy('rejected_help'));
        }

        if ($this->club->verification_notes) {
            $message->line($copy('notes', ['notes' => $this->club->verification_notes]));
        }

        return $message->action($copy('action'), route('auth.clubs.show', $this->club->id));
    }
}
