<?php

namespace App\Notifications;

use App\Models\ClubSubscription;
use App\Models\UserSubscription;
use App\Support\EmailTemplate;
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

        return EmailTemplate::mail('subscription_ending_soon', [
            'name' => $this->recipientName($notifiable),
            'plan_name' => $subscription->plan?->name ?? 'Airmius Plan',
            'end_date' => $this->date($endsAt),
            'ending_message' => $isTrial
                ? 'deine Airmius Testphase läuft bald ab.'
                : 'dein Airmius Abo läuft bald ab.',
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
