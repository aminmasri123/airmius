<?php

namespace App\Notifications;

use App\Models\CommerceReturnRequest;
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

        return (new MailMessage)
            ->subject('Ruecksendung aktualisiert')
            ->greeting('Hallo '.($notifiable->name ?? ''))
            ->line('Deine Ruecksendung zu '.($return->item?->title ?: 'deiner Bestellung').' wurde aktualisiert.')
            ->line('Status: '.$return->status)
            ->line($return->resolution_note ?: 'Du kannst den aktuellen Stand in deinem Marketplace-Bereich sehen.')
            ->action('Marketplace oeffnen', route('auth.commerce.index'));
    }
}
