<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Support\LocalizedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClubInvoiceReminder extends Notification
{
    use Queueable;

    public function __construct(private readonly Invoice $invoice, private readonly int $stage) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = LocalizedMail::for($notifiable);
        $invoice = $this->invoice->loadMissing('club:id,name');

        return (new MailMessage)
            ->subject($mail->text('invoice.reminder_subject', ['invoice' => $invoice->number]))
            ->greeting($mail->greeting($notifiable))
            ->line($mail->text('invoice.reminder_body', ['invoice' => $invoice->number]))
            ->line($mail->text('common.fields.amount', ['value' => $mail->money((float) $invoice->amount, 'EUR')]))
            ->line($mail->text('common.fields.due_on', ['value' => $mail->date($invoice->due_date)]))
            ->action($mail->text('common.actions.invoice'), route('auth.club-memberships.index'))
            ->line('Mahnstufe '.$this->stage);
    }
}
