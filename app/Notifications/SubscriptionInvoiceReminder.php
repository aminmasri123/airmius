<?php

namespace App\Notifications;

use App\Models\SubscriptionInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionInvoiceReminder extends Notification
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
            ->subject('Erinnerung: Airmius Rechnung '.$invoice->number.' ist offen')
            ->greeting('Hallo '.$this->recipientName($notifiable).',')
            ->line('für deine Airmius Rechnung ist noch keine Zahlung verbucht.')
            ->line('Rechnung: '.$invoice->number)
            ->line('Plan: '.($invoice->plan?->name ?? $invoice->title))
            ->line('Betrag: '.$this->amount($invoice))
            ->line('Fällig seit: '.$this->date($invoice->due_at))
            ->line('Verwendungszweck: '.($invoice->payment_reference ?: $invoice->number))
            ->action('Rechnung herunterladen', route('auth.subscription-invoices.download', $invoice))
            ->line('Falls du bereits bezahlt hast, kannst du diese Erinnerung ignorieren. Die Zahlung wird nach dem Bankabgleich markiert.');
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
