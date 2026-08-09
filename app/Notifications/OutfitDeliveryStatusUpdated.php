<?php

namespace App\Notifications;

use App\Models\OutfitDelivery;
use App\Support\LocalizedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OutfitDeliveryStatusUpdated extends Notification
{
    use Queueable;

    public function __construct(private OutfitDelivery $delivery) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $delivery = $this->delivery->loadMissing(['subscription.plan']);
        $subscription = $delivery->subscription;
        $mail = LocalizedMail::for($notifiable);
        $status = in_array($delivery->status, ['preparing', 'shipped', 'delivered', 'cancelled'], true)
            ? $delivery->status
            : 'planned';

        $message = (new MailMessage)
            ->subject($mail->text('outfit.delivery_subjects.'.$status))
            ->greeting($mail->greeting($notifiable))
            ->line($mail->text('outfit.delivery_bodies.'.$status))
            ->line($mail->text('common.fields.subscription', ['value' => $subscription?->plan?->name ?? $mail->text('outfit.plan_fallback')]))
            ->line($mail->text('common.fields.delivery_month', ['value' => $mail->date($delivery->delivery_month)]));

        if ($delivery->carrier || $delivery->tracking_number) {
            $message->line($mail->text('common.fields.shipping', ['value' => trim(($delivery->carrier ?: $mail->text('outfit.carrier_fallback')).' '.($delivery->tracking_number ?: ''))]));
        }

        if ($delivery->tracking_url) {
            $message->line($mail->text('common.fields.tracking_link', ['value' => $delivery->tracking_url]));
        }

        if ($delivery->notes) {
            $message->line($mail->text('common.fields.note', ['value' => $delivery->notes]));
        }

        return $message->action($mail->text('common.actions.outfit'), route('auth.outfit-subscriptions.index'));
    }
}
