<?php

namespace App\Notifications;

use App\Models\OutfitSubscription;
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
        $amount = number_format(max(0, ((int) $subscription->monthly_price_cents - (int) $subscription->sponsor_discount_cents)) / 100, 2, ',', '.').' '.$subscription->currency;

        return (new MailMessage)
            ->subject('Erinnerung: Outfit-Abo Zahlung offen')
            ->greeting('Hallo '.(trim((string) ($notifiable->name ?? '')) ?: 'zusammen').',')
            ->line('für dein Outfit-Abo ist noch keine Zahlung eingegangen.')
            ->line('Abo: '.($subscription->plan?->name ?? 'Outfit-Abo'))
            ->line('Betrag: '.$amount)
            ->line('Fällig bis: '.$this->date($subscription->payment_due_at))
            ->line('Zahlungsreferenz: '.($subscription->payment_reference ?: '-'))
            ->action('Outfit-Abo ansehen', route('auth.outfit-subscriptions.index'));
    }

    private function date($value): string
    {
        return $value ? $value->format('d.m.Y') : 'noch nicht gesetzt';
    }
}
