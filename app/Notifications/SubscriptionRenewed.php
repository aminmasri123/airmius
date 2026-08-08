<?php

namespace App\Notifications;

use App\Models\ClubSubscription;
use App\Models\UserSubscription;
use App\Support\EmailTemplate;
use App\Support\SupportedLocale;
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
        $locale = SupportedLocale::normalize($notifiable->language ?? null) ?? SupportedLocale::DEFAULT;

        return EmailTemplate::mail('subscription_renewed', [
            'name' => $this->recipientName($notifiable, $locale),
            'plan_name' => $subscription->plan?->name ?? __('subscription.email.plan_fallback', locale: $locale),
            'end_date' => $this->date($subscription->current_period_ends_at, $locale),
        ], route('guest.pricing'), $locale);
    }

    private function date($value, string $locale): string
    {
        return $value ? $value->format($locale === 'en' ? 'm/d/Y' : ($locale === 'ar' ? 'Y/m/d' : 'd.m.Y')) : '-';
    }

    private function recipientName(object $notifiable, string $locale): string
    {
        return trim((string) ($notifiable->name ?? ''))
            ?: __('data_erasure.email_templates.together', locale: $locale);
    }
}
