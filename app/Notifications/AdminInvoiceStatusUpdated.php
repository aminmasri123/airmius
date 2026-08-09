<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Support\LocalizedMail;
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
        $mail = LocalizedMail::for($notifiable);

        $message = (new MailMessage)
            ->subject($mail->text('invoice.status_subject'))
            ->greeting($mail->greeting($notifiable))
            ->line($mail->text('invoice.status_body'))
            ->line($mail->text('common.fields.invoice_number', ['value' => $invoice->number]))
            ->line($mail->text('common.fields.title', ['value' => $invoice->title]))
            ->line($mail->text('common.fields.old_status', ['value' => $this->statusLabel($mail, $this->oldStatus)]))
            ->line($mail->text('common.fields.new_status', ['value' => $this->statusLabel($mail, $invoice->status)]))
            ->line($mail->text('common.fields.amount', ['value' => $mail->money((float) $invoice->amount, 'EUR')]))
            ->line($mail->text('common.fields.due_on', ['value' => $mail->date($invoice->due_date)]))
            ->action($mail->text('common.actions.invoice'), route('auth.settings').'#billing')
            ->line($mail->text('invoice.status_review'));

        if ($this->mailer) {
            $message->mailer($this->mailer);
        }

        if ($this->fromAddress) {
            $message->from($this->fromAddress, $this->fromName ?: config('mail.from.name'));
        }

        return $message;
    }

    private function statusLabel(LocalizedMail $mail, ?string $status): string
    {
        return in_array($status, ['paid', 'open', 'pending', 'awaiting_transfer', 'overdue', 'cancelled', 'failed'], true)
            ? $mail->text('invoice.statuses.'.$status)
            : ($status ?: '-');
    }
}
