<?php

namespace App\Notifications;

use App\Models\CommerceReturnRequest;
use App\Support\LocalizedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CommerceReturnStatusUpdated extends Notification
{
    use Queueable;

    public function __construct(private CommerceReturnRequest $returnRequest) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $return = $this->returnRequest->loadMissing(['order', 'item']);
        $mail = LocalizedMail::for($notifiable);
        $statusKey = 'commerce_return.statuses.'.(string) $return->status;
        $status = $mail->text($statusKey);
        $status = $status === 'core_mail.'.$statusKey ? (string) $return->status : $status;

        return (new MailMessage)
            ->subject($mail->text('commerce_return.subject'))
            ->greeting($mail->greeting($notifiable))
            ->line($mail->text('commerce_return.body', ['item' => $return->item?->title ?: $mail->text('commerce_return.item_fallback')]))
            ->line($mail->text('common.fields.status', ['value' => $status]))
            ->line($return->resolution_note ?: $mail->text('commerce_return.default_note'))
            ->action($mail->text('common.actions.marketplace'), route('auth.commerce.index'));
    }
}
