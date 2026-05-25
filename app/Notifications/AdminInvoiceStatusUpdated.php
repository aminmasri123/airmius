<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminInvoiceStatusUpdated extends Notification
{
    use Queueable;

    public function __construct(
        private Invoice $invoice,
        private ?string $oldStatus = null,
        private ?string $mailer = null,
        private ?string $fromAddress = null,
        private ?string $fromName = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invoice = $this->invoice->loadMissing(['club:id,name']);

        $message = (new MailMessage)
            ->subject('Status deiner Rechnung wurde aktualisiert')
            ->greeting('Hallo '.$this->recipientName($notifiable).',')
            ->line('der Status deiner Rechnung wurde aktualisiert.')
            ->line('Rechnungsnummer: '.$invoice->number)
            ->line('Titel: '.$invoice->title)
            ->line('Vorheriger Status: '.$this->statusLabel($this->oldStatus))
            ->line('Neuer Status: '.$this->statusLabel($invoice->status))
            ->line('Betrag: '.$this->amount($invoice))
            ->line('Fällig bis: '.$this->date($invoice->due_date))
            ->action('Rechnung ansehen', route('auth.settings').'#billing')
            ->line('Bitte prüfe deine Rechnungsübersicht, falls noch eine Zahlung offen ist.');

        if ($this->mailer) {
            $message->mailer($this->mailer);
        }

        if ($this->fromAddress) {
            $message->from($this->fromAddress, $this->fromName ?: config('mail.from.name'));
        }

        return $message;
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'paid' => 'Bezahlt',
            'open' => 'Offen',
            'pending' => 'Ausstehend',
            'awaiting_transfer' => 'Wartet auf Überweisung',
            'overdue' => 'Überfällig',
            'cancelled' => 'Storniert',
            'failed' => 'Fehlgeschlagen',
            default => $status ?: '-',
        };
    }

    private function amount(Invoice $invoice): string
    {
        return number_format((float) $invoice->amount, 2, ',', '.').' EUR';
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
