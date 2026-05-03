<?php

namespace App\Notifications;

use App\Models\ClubSubscription;
use App\Models\UserSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionPaymentIssue extends Notification
{
    use Queueable;

    public function __construct(private ClubSubscription|UserSubscription $subscription) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subscription = $this->subscription->loadMissing(['plan']);

        return (new MailMessage)
            ->subject('Zahlung für dein Airmius Abo ist offen')
            ->greeting('Hallo '.$this->recipientName($notifiable).',')
            ->line('für dein Airmius Abo ist eine Zahlung offen oder deine Testphase ist abgelaufen.')
            ->line('Plan: '.($subscription->plan?->name ?? 'Airmius Plan'))
            ->line('Status: Zahlung offen')
            ->action('Plan verlängern', route('guest.pricing'))
            ->line('Bitte aktualisiere die Zahlung, damit alle gebuchten Funktionen aktiv bleiben.');
    }

    private function recipientName(object $notifiable): string
    {
        return trim((string) ($notifiable->name ?? '')) ?: 'zusammen';
    }
}
