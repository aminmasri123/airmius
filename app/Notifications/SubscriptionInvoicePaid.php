<?php

namespace App\Notifications;

use App\Models\SubscriptionInvoice;
use App\Support\EmailTemplate;
use App\Support\SupportedLocale;
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
        $locale = SupportedLocale::normalize($notifiable->language ?? null) ?? SupportedLocale::DEFAULT;

        return EmailTemplate::mail('subscription_invoice_paid', [
            'name' => $this->recipientName($notifiable, $locale),
            'invoice_number' => $invoice->number,
            'plan_name' => $invoice->plan?->name ?? $invoice->title,
            'amount' => $this->amount($invoice, $locale),
            'paid_date' => $this->date($invoice->paid_at, $locale),
            'payment_method' => $this->paymentMethod($invoice->payment_method, $locale),
        ], route('auth.subscription-invoices.download', $invoice), $locale);
    }

    private function amount(SubscriptionInvoice $invoice, string $locale): string
    {
        $separators = $locale === 'en' ? ['.', ','] : [',', $locale === 'fr' ? ' ' : '.'];

        return number_format($invoice->amount_cents / 100, 2, ...$separators).' '.$invoice->currency;
    }

    private function date($value, string $locale): string
    {
        return $value ? $value->format($locale === 'en' ? 'm/d/Y' : ($locale === 'ar' ? 'Y/m/d' : 'd.m.Y')) : '-';
    }

    private function paymentMethod(?string $method, string $locale): string
    {
        return match ($method) {
            'stripe' => 'Stripe',
            'paypal' => 'PayPal',
            'bank_transfer' => __('subscription.invoice.payment_bank_transfer', locale: $locale),
            default => $method ?: __('subscription.invoice.payment_unknown', locale: $locale),
        };
    }

    private function recipientName(object $notifiable, string $locale): string
    {
        return trim((string) ($notifiable->name ?? ''))
            ?: __('data_erasure.email_templates.together', locale: $locale);
    }
}
