<?php

namespace App\Notifications;

use App\Models\Setting;
use App\Models\SubscriptionInvoice;
use App\Support\EmailTemplate;
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

        return EmailTemplate::mail('subscription_invoice_awaiting_transfer', [
            'name' => $this->recipientName($notifiable),
            'invoice_number' => $invoice->number,
            'plan_name' => $invoice->plan?->name ?? $invoice->title,
            'amount' => $this->amount($invoice),
            'due_date' => $this->date($invoice->due_at),
            'payment_reference' => $invoice->payment_reference ?: $invoice->number,
            'bank_account_holder' => $bank['bank_account_holder'],
            'bank_name' => $bank['bank_name'] ?: '-',
            'iban' => $bank['iban'] ?: '-',
            'bic' => $bank['bic'] ?: '-',
        ], route('auth.subscription-invoices.download', $invoice));
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

    private function amount(SubscriptionInvoice $invoice): string
    {
        return number_format($invoice->amount_cents / 100, 2, ',', '.').' '.$invoice->currency;
    }

    private function date($value): string
    {
        return $value ? $value->format('d.m.Y') : '-';
    }

    private function recipientName(object $notifiable): string
    {
        return trim((string) ($notifiable->name ?? '')) ?: 'zusammen';
    }
}
