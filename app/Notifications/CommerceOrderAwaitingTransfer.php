<?php

namespace App\Notifications;

use App\Models\CommerceOrder;
use App\Support\EmailTemplate;
use App\Support\SupportedLocale;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CommerceOrderAwaitingTransfer extends Notification
{
    use Queueable;

    public function __construct(
        private CommerceOrder $order,
        private string $actionUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order->loadMissing(['items.orderable', 'orderable']);
        $bank = data_get($order->payload, 'bank_transfer', []);
        $locale = SupportedLocale::normalize($notifiable->language ?? null)
            ?? SupportedLocale::normalize(app()->getLocale())
            ?? SupportedLocale::DEFAULT;

        return EmailTemplate::mail('commerce_order_awaiting_transfer', [
            'name' => trim((string) ($notifiable->name ?? $order->guest_name ?? '')),
            'order_number' => '#'.$order->id,
            'order_title' => $this->orderTitle($order),
            'amount' => $this->amount($order),
            'due_date' => $order->due_at?->format('d.m.Y') ?: '-',
            'payment_reference' => $order->payment_reference ?: '-',
            'bank_account_holder' => data_get($bank, 'bank_account_holder', '-'),
            'bank_name' => data_get($bank, 'bank_name', '-'),
            'iban' => data_get($bank, 'iban', '-'),
            'bic' => data_get($bank, 'bic', '-'),
        ], $this->actionUrl, $locale);
    }

    private function orderTitle(CommerceOrder $order): string
    {
        $titles = $order->items
            ->pluck('title')
            ->filter()
            ->unique()
            ->values();

        return $titles->isNotEmpty()
            ? $titles->join(', ')
            : ($order->orderable?->name ?? $order->orderable?->title ?? 'Airmius Marketplace');
    }

    private function amount(CommerceOrder $order): string
    {
        return ($order->currency ?: 'EUR').' '.number_format($order->amount_cents / 100, 2, '.', ',');
    }
}
