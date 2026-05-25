<?php

namespace App\Notifications;

use App\Models\OutfitSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OutfitPaymentDunningNotice extends Notification
{
    use Queueable;

    public function __construct(
        private OutfitSubscription $subscription,
        private int $level,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subscription = $this->subscription->loadMissing('plan');
        $amount = number_format(max(0, ((int) $subscription->monthly_price_cents - (int) $subscription->sponsor_discount_cents)) / 100, 2, ',', '.').' '.$subscription->currency;
        $isFinal = $this->level >= 3;

        $message = (new MailMessage)
            ->subject($isFinal ? 'Letzte Mahnung: Outfit-Abo Zahlung offen' : $this->level.'. Mahnung: Outfit-Abo Zahlung offen')
            ->greeting('Hallo '.(trim((string) ($notifiable->name ?? '')) ?: 'zusammen').',')
            ->line('für dein laufendes Outfit-Abo ist eine Zahlung offen.')
            ->line('Abo: '.($subscription->plan?->name ?? 'Outfit-Abo'))
            ->line('Betrag: '.$amount)
            ->line('Fällig seit: '.$this->date($subscription->payment_due_at))
            ->line('Zahlungsreferenz: '.($subscription->payment_reference ?: '-'));

        if ($isFinal) {
            $message->line('Dein Outfit-Abo wurde bis zum Zahlungseingang pausiert. In dieser Zeit werden keine weiteren Lieferungen vorbereitet.');
        } else {
            $message->line('Bitte gleiche die Zahlung aus, damit dein Outfit-Abo ohne Unterbrechung weiterlaufen kann.');
        }

        return $message->action('Outfit-Abo ansehen', route('auth.outfit-subscriptions.index'));
    }

    private function date($value): string
    {
        return $value ? $value->format('d.m.Y') : 'noch nicht gesetzt';
    }
}
