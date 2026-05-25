<?php

namespace App\Notifications;

use App\Models\OutfitSubscription;
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

        return (new MailMessage)
            ->subject('Outfit-Abo Anfrage wurde gelöscht')
            ->greeting('Hallo '.(trim((string) ($notifiable->name ?? '')) ?: 'zusammen').',')
            ->line('deine Outfit-Abo Anfrage wurde gelöscht, weil nach 9 Tagen keine Zahlung eingegangen ist.')
            ->line('Abo: '.($subscription->plan?->name ?? 'Outfit-Abo'))
            ->line('Zahlungsreferenz: '.($subscription->payment_reference ?: '-'))
            ->line('Du kannst jederzeit eine neue Anfrage starten, wenn du das Outfit-Abo weiterhin nutzen möchtest.')
            ->action('Outfit-Abos ansehen', route('auth.outfit-subscriptions.index'));
    }
}
