<?php

namespace App\Notifications;

use App\Models\ClubSubscription;
use App\Models\UserSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionEndingSoon extends Notification
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
        $isTrial = $subscription->status === 'trialing';
        $endsAt = $subscription->current_period_ends_at ?? $subscription->trial_ends_at;

        return (new MailMessage)
            ->subject($isTrial ? 'Deine Airmius Testphase endet bald' : 'Dein Airmius Abo endet bald')
            ->greeting('Hallo '.$this->recipientName($notifiable).',')
            ->line($isTrial
                ? 'deine Airmius Testphase läuft bald ab.'
                : 'dein Airmius Abo läuft bald ab.')
            ->line('Plan: '.($subscription->plan?->name ?? 'Airmius Plan'))
            ->line('Enddatum: '.$this->date($endsAt))
            ->action('Pläne ansehen', route('guest.pricing'))
            ->line('Wenn du Airmius weiter nutzen möchtest, kannst du rechtzeitig einen passenden Plan wählen.');
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
