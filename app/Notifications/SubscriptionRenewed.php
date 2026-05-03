<?php

namespace App\Notifications;

use App\Models\ClubSubscription;
use App\Models\UserSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionRenewed extends Notification
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
            ->subject('Airmius Abo wurde verlaengert')
            ->greeting('Hallo '.$this->recipientName($notifiable).',')
            ->line('dein Airmius Abo wurde verlaengert.')
            ->line('Plan: '.($subscription->plan?->name ?? 'Airmius Plan'))
            ->line('Neue Laufzeit bis: '.$this->date($subscription->current_period_ends_at))
            ->action('Plaene ansehen', route('guest.pricing'))
            ->line('Danke, dass du Airmius nutzt.');
    }

    private function date($value): string
    {
        return $value ? $value->format('d.m.Y') : '-';
    }

    private function recipientName(object $notifiable): string
    {
        return trim((string) ($notifiable->name ?? '')) ?: 'zusammen';
    }
}
