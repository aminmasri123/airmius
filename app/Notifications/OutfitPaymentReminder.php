<?php

namespace App\Notifications;

use App\Models\OutfitSubscription;
use App\Support\LocalizedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OutfitPaymentReminder extends Notification
{
    use Queueable;

    public function __construct(private OutfitSubscription $subscription) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subscription = $this->subscription->loadMissing('plan');
        $mail = LocalizedMail::for($notifiable);
        $amount = $mail->money(
            max(0, ((int) $subscription->monthly_price_cents - (int) $subscription->sponsor_discount_cents)) / 100,
            (string) $subscription->currency,
        );

        return (new MailMessage)
            ->subject($mail->text('outfit.payment_reminder_subject'))
            ->greeting($mail->greeting($notifiable))
            ->line($mail->text('outfit.payment_reminder_body'))
            ->line($mail->text('common.fields.subscription', ['value' => $subscription->plan?->name ?? $mail->text('outfit.plan_fallback')]))
            ->line($mail->text('common.fields.amount', ['value' => $amount]))
            ->line($mail->text('common.fields.due_on', ['value' => $mail->date($subscription->payment_due_at)]))
            ->line($mail->text('common.fields.payment_reference', ['value' => $subscription->payment_reference ?: $mail->text('common.not_set')]))
            ->action($mail->text('common.actions.outfit'), route('auth.outfit-subscriptions.index'));
    }
}
