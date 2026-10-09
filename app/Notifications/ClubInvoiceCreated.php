<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Support\LocalizedMail;
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
        $club = $invoice->club;
        $mail = LocalizedMail::for($notifiable);
        $clubName = $club?->name ?? $mail->text('invoice.club_fallback');

        $message = (new MailMessage)
            ->subject($mail->text('invoice.new_subject', ['sender' => $clubName]))
            ->greeting($mail->greeting($notifiable))
            ->line($mail->text('invoice.club_body', ['club' => $clubName]))
            ->line($mail->text('common.fields.invoice_number', ['value' => $invoice->number]))
            ->line($mail->text('common.fields.title', ['value' => $invoice->title]))
            ->line($mail->text('common.fields.amount', ['value' => $mail->money((float) $invoice->amount, 'EUR')]))
            ->line($mail->text('common.fields.due_on', ['value' => $mail->date($invoice->due_date)]))
            ->when(filled($invoice->description), fn (MailMessage $message) => $message->line($invoice->description))
            ->when(filled($club?->sepa_iban), fn (MailMessage $message) => $message
                ->line($mail->text('invoice.bank_transfer'))
                ->line($mail->text('common.fields.account_holder', ['value' => $club->sepa_account_holder ?: $clubName]))
                ->line($mail->text('common.fields.iban', ['value' => $club->sepa_iban]))
                ->when(filled($club->sepa_bic), fn (MailMessage $message) => $message->line($mail->text('common.fields.bic', ['value' => $club->sepa_bic])))
                ->line($mail->text('common.fields.purpose', ['value' => $invoice->payment_reference ?: $invoice->number])))
            ->action($mail->text('common.actions.invoice'), route('auth.settings', ['tab' => 'billing']).'#billing')
            ->line($mail->text('invoice.settle'));

        if ($this->mailer) {
            $message->mailer($this->mailer);
        }

        if ($this->fromAddress) {
            $message->from($this->fromAddress, $this->fromName ?: config('mail.from.name'));
        }

        return $message;
    }
}
