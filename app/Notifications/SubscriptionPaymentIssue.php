<?php

namespace App\Notifications;

use App\Models\ClubSubscription;
use App\Models\UserSubscription;
use App\Support\EmailTemplate;
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

        return EmailTemplate::mail('subscription_payment_issue', [
            'name' => $this->recipientName($notifiable),
            'plan_name' => $subscription->plan?->name ?? 'Airmius Plan',
        ], route('guest.pricing'));
    }

    private function recipientName(object $notifiable): string
    {
        return trim((string) ($notifiable->name ?? '')) ?: 'zusammen';
    }
}
