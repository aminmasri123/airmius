<?php

namespace App\Notifications;

use App\Models\CommerceOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CommerceOrderCompleted extends Notification
{
    use Queueable;

    public function __construct(private CommerceOrder $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order->loadMissing('orderable');
        $title = $order->orderable?->name ?? $order->orderable?->title ?? 'Airmius Bestellung';

        return (new MailMessage)
            ->subject('Airmius Bestellung bestaetigt')
            ->greeting('Hallo '.(trim((string) ($notifiable->name ?? '')) ?: 'zusammen').',')
            ->line('deine Bestellung wurde erfolgreich bestaetigt.')
            ->line('Bestellung: '.$title)
            ->line('Betrag: '.number_format($order->amount_cents / 100, 2, ',', '.').' '.$order->currency)
            ->line('Status: bezahlt')
            ->action('Marketplace ansehen', route('auth.commerce.index'))
            ->line('Danke, dass du Airmius nutzt.');
    }
}
