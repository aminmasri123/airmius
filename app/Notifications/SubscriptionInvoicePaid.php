<?php

namespace App\Notifications;

use App\Models\SubscriptionInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionInvoicePaid extends Notification
{
    use Queueable;

    public function __construct(private SubscriptionInvoice $invoice) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invoice = $this->invoice->loadMissing(['plan:id,name', 'club:id,name']);

        return (new MailMessage)
            ->subject('Zahlung für Airmius Rechnung '.$invoice->number.' bestätigt')
            ->greeting('Hallo '.$this->recipientName($notifiable).',')
            ->line('deine Zahlung wurde bestaetigt. Dein Airmius Abo ist aktiv.')
            ->line('Rechnung: '.$invoice->number)
            ->line('Plan: '.($invoice->plan?->name ?? $invoice->title))
            ->line('Betrag: '.$this->amount($invoice))
            ->line('Bezahlt am: '.$this->date($invoice->paid_at))
            ->line('Zahlungsart: '.$this->paymentMethod($invoice->payment_method))
            ->action('Rechnung herunterladen', route('auth.subscription-invoices.download', $invoice))
            ->line('Danke, dass du Airmius nutzt.');
    }

    private function amount(SubscriptionInvoice $invoice): string
    {
        return number_format($invoice->amount_cents / 100, 2, ',', '.').' '.$invoice->currency;
    }

    private function date($value): string
    {
        return $value ? $value->format('d.m.Y') : '-';
    }

    private function paymentMethod(?string $method): string
    {
        return match ($method) {
            'stripe' => 'Stripe',
            'paypal' => 'PayPal',
            'bank_transfer' => 'Ueberweisung',
            default => $method ?: '-',
        };
    }

    private function recipientName(object $notifiable): string
    {
        return trim((string) ($notifiable->name ?? '')) ?: 'zusammen';
    }
}
