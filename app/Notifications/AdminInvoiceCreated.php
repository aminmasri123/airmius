<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Support\LocalizedMail;
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
        $mail = LocalizedMail::for($notifiable);

        $message = (new MailMessage)
            ->subject($mail->text('invoice.new_subject', ['sender' => $sender]))
            ->greeting($mail->greeting($notifiable))
            ->line($mail->text('invoice.new_body'))
            ->line($mail->text('common.fields.invoice_number', ['value' => $invoice->number]))
            ->line($mail->text('common.fields.title', ['value' => $invoice->title]))
            ->line($mail->text('common.fields.amount', ['value' => $mail->money((float) $invoice->amount, 'EUR')]))
            ->line($mail->text('common.fields.due_on', ['value' => $mail->date($invoice->due_date)]))
            ->when(filled($invoice->description), fn (MailMessage $message) => $message->line($invoice->description))
            ->action($mail->text('common.actions.invoice'), route('auth.settings').'#billing')
            ->line($mail->text('invoice.settle_if_open'));

        if ($this->mailer) {
            $message->mailer($this->mailer);
        }

        if ($this->fromAddress) {
            $message->from($this->fromAddress, $this->fromName ?: config('mail.from.name'));
        }

        return $message;
    }
}
