<?php

namespace App\Notifications;

use App\Models\OutfitSubscription;
use App\Support\LocalizedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OutfitPaymentExpired extends Notification
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

        return (new MailMessage)
            ->subject($mail->text('outfit.expired_subject'))
            ->greeting($mail->greeting($notifiable))
            ->line($mail->text('outfit.expired_body'))
            ->line($mail->text('common.fields.subscription', ['value' => $subscription->plan?->name ?? $mail->text('outfit.plan_fallback')]))
            ->line($mail->text('common.fields.payment_reference', ['value' => $subscription->payment_reference ?: $mail->text('common.not_set')]))
            ->line($mail->text('outfit.expired_restart'))
            ->action($mail->text('common.actions.outfits'), route('auth.outfit-subscriptions.index'));
    }
}
