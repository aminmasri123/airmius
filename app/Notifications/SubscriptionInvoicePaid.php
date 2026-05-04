<?php

namespace App\Notifications;

use App\Models\SubscriptionInvoice;
use App\Support\EmailTemplate;
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

        return EmailTemplate::mail('subscription_invoice_paid', [
            'name' => $this->recipientName($notifiable),
            'invoice_number' => $invoice->number,
            'plan_name' => $invoice->plan?->name ?? $invoice->title,
            'amount' => $this->amount($invoice),
            'paid_date' => $this->date($invoice->paid_at),
            'payment_method' => $this->paymentMethod($invoice->payment_method),
        ], route('auth.subscription-invoices.download', $invoice));
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
