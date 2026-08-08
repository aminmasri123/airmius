<?php

namespace App\Notifications;

use App\Models\Setting;
use App\Models\SubscriptionInvoice;
use App\Support\EmailTemplate;
use App\Support\SupportedLocale;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionInvoiceAwaitingTransfer extends Notification
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
        $bank = $this->bankSettings();
        $locale = SupportedLocale::normalize($notifiable->language ?? null) ?? SupportedLocale::DEFAULT;

        return EmailTemplate::mail('subscription_invoice_awaiting_transfer', [
            'name' => $this->recipientName($notifiable, $locale),
            'invoice_number' => $invoice->number,
            'plan_name' => $invoice->plan?->name ?? $invoice->title,
            'amount' => $this->amount($invoice, $locale),
            'due_date' => $this->date($invoice->due_at, $locale),
            'payment_reference' => $invoice->payment_reference ?: $invoice->number,
            'bank_account_holder' => $bank['bank_account_holder'],
            'bank_name' => $bank['bank_name'] ?: '-',
            'iban' => $bank['iban'] ?: '-',
            'bic' => $bank['bic'] ?: '-',
        ], route('auth.subscription-invoices.download', $invoice), $locale);
    }

    private function bankSettings(): array
    {
        return [
            'bank_account_holder' => Setting::valueFor('billing_bank_account_holder', 'Airmius'),
            'bank_name' => Setting::valueFor('billing_bank_name', ''),
            'iban' => Setting::valueFor('billing_iban', ''),
            'bic' => Setting::valueFor('billing_bic', ''),
        ];
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

    private function recipientName(object $notifiable, string $locale): string
    {
        return trim((string) ($notifiable->name ?? ''))
            ?: __('data_erasure.email_templates.together', locale: $locale);
    }
}
