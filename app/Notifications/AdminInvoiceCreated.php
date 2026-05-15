<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminInvoiceCreated extends Notification
{
    use Queueable;

    public function __construct(
        private Invoice $invoice,
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
        $sender = $invoice->club?->name ?: 'Airmius';

        $message = (new MailMessage)
            ->subject('Neue Rechnung von '.$sender)
            ->greeting('Hallo '.$this->recipientName($notifiable).',')
            ->line('du hast eine neue Rechnung erhalten.')
            ->line('Rechnungsnummer: '.$invoice->number)
            ->line('Titel: '.$invoice->title)
            ->line('Betrag: '.$this->amount($invoice))
            ->line('Faellig bis: '.$this->date($invoice->due_date))
            ->when(filled($invoice->description), fn (MailMessage $message) => $message->line($invoice->description))
            ->action('Rechnung ansehen', route('auth.settings').'#billing')
            ->line('Bitte pruefe die Rechnung und begleiche sie fristgerecht, falls sie noch offen ist.');

        if ($this->mailer) {
            $message->mailer($this->mailer);
        }

        if ($this->fromAddress) {
            $message->from($this->fromAddress, $this->fromName ?: config('mail.from.name'));
        }

        return $message;
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
