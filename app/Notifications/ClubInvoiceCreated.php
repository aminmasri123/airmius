<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClubInvoiceCreated extends Notification
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
        $invoice = $this->invoice->loadMissing('club:id,name,sepa_account_holder,sepa_iban,sepa_bic');
        $clubName = $invoice->club?->name ?? 'deinem Verein';
        $club = $invoice->club;

        $message = (new MailMessage)
            ->subject('Neue Rechnung von '.$clubName)
            ->greeting('Hallo '.$this->recipientName($notifiable).',')
            ->line('du hast eine neue Rechnung von '.$clubName.' erhalten.')
            ->line('Rechnungsnummer: '.$invoice->number)
            ->line('Titel: '.$invoice->title)
            ->line('Betrag: '.$this->amount($invoice))
            ->line('Fällig bis: '.$this->date($invoice->due_date))
            ->when(filled($invoice->description), fn (MailMessage $message) => $message->line($invoice->description))
            ->when(filled($club?->sepa_iban), fn (MailMessage $message) => $message
                ->line('Zahlung per Überweisung:')
                ->line('Kontoinhaber: '.($club->sepa_account_holder ?: $clubName))
                ->line('IBAN: '.$club->sepa_iban)
                ->when(filled($club->sepa_bic), fn (MailMessage $mail) => $mail->line('BIC: '.$club->sepa_bic))
                ->line('Verwendungszweck: '.($invoice->payment_reference ?: $invoice->number)))
            ->action('Rechnung ansehen', route('auth.club-memberships.index'))
            ->line('Bitte prüfe die Rechnung und begleiche sie fristgerecht.');

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
