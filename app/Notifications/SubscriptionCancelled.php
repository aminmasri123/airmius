<?php

namespace App\Notifications;

use App\Models\ClubSubscription;
use App\Models\UserSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionCancelled extends Notification
{
    use Queueable;

    public function __construct(
        private ClubSubscription|UserSubscription $subscription,
        private string $mode = 'period_end'
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subscription = $this->subscription->loadMissing(['plan']);
        $endsAt = $this->mode === 'now'
            ? $subscription->cancelled_at
            : ($subscription->cancels_at ?? $subscription->current_period_ends_at);

        return (new MailMessage)
            ->subject('Airmius Abo-Kuendigung bestaetigt')
            ->greeting('Hallo '.$this->recipientName($notifiable).',')
            ->line($this->mode === 'now'
                ? 'dein Airmius Abo wurde beendet.'
                : 'deine Airmius Abo-Kuendigung wurde zum Periodenende vorgemerkt.')
            ->line('Plan: '.($subscription->plan?->name ?? 'Airmius Plan'))
            ->line('Endet am: '.$this->date($endsAt))
            ->action('Plaene ansehen', route('guest.pricing'))
            ->line('Du kannst später jederzeit wieder einen passenden Plan aktivieren.');
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
