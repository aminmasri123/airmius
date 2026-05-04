<?php

namespace App\Notifications;

use App\Models\CommerceOrder;
use App\Support\EmailTemplate;
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

        return EmailTemplate::mail('commerce_order_completed', [
            'name' => trim((string) ($notifiable->name ?? '')) ?: 'zusammen',
            'order_title' => $title,
            'amount' => number_format($order->amount_cents / 100, 2, ',', '.').' '.$order->currency,
        ], route('guest.marketplace'));
    }
}
