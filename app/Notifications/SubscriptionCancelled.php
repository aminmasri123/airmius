<?php

namespace App\Notifications;

use App\Models\ClubSubscription;
use App\Models\UserSubscription;
use App\Support\EmailTemplate;
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

        return EmailTemplate::mail('subscription_cancelled', [
            'name' => $this->recipientName($notifiable),
            'plan_name' => $subscription->plan?->name ?? 'Airmius Plan',
            'end_date' => $this->date($endsAt),
            'cancel_message' => $this->mode === 'now'
                ? 'dein Airmius Abo wurde beendet.'
                : 'deine Airmius Abo-Kündigung wurde zum Periodenende vorgemerkt.',
        ], route('guest.pricing'));
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
