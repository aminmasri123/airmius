<?php

namespace App\Notifications;

use App\Models\Setting;
use App\Models\SubscriptionInvoice;
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

        return (new MailMessage)
            ->subject('Airmius Rechnung '.$invoice->number.' wartet auf Ueberweisung')
            ->greeting('Hallo '.$this->recipientName($notifiable).',')
            ->line('deine Airmius Rechnung wurde erstellt und wartet auf Zahlung per Ueberweisung.')
            ->line('Rechnung: '.$invoice->number)
            ->line('Plan: '.($invoice->plan?->name ?? $invoice->title))
            ->line('Betrag: '.$this->amount($invoice))
            ->line('Fällig bis: '.$this->date($invoice->due_at))
            ->line('Verwendungszweck: '.($invoice->payment_reference ?: $invoice->number))
            ->line('Kontoinhaber: '.$bank['bank_account_holder'])
            ->line('Bank: '.($bank['bank_name'] ?: '-'))
            ->line('IBAN: '.($bank['iban'] ?: '-'))
            ->line('BIC: '.($bank['bic'] ?: '-'))
            ->action('Rechnung herunterladen', route('auth.subscription-invoices.download', $invoice))
            ->line('Sobald die Zahlung eingegangen ist, bestaetigen wir sie in Airmius.');
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
