<?php

namespace App\Notifications;

use App\Models\OutfitDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OutfitDeliveryStatusUpdated extends Notification
{
    use Queueable;

    public function __construct(private OutfitDelivery $delivery) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $delivery = $this->delivery->loadMissing(['subscription.plan']);
        $subscription = $delivery->subscription;

        $message = (new MailMessage)
            ->subject($this->subject($delivery->status))
            ->greeting('Hallo '.(trim((string) ($notifiable->name ?? '')) ?: 'zusammen').',')
            ->line($this->body($delivery->status))
            ->line('Abo: '.($subscription?->plan?->name ?? 'Outfit-Abo'))
            ->line('Liefermonat: '.$this->date($delivery->delivery_month));

        if ($delivery->carrier || $delivery->tracking_number) {
            $message->line('Versand: '.trim(($delivery->carrier ?: 'Paketdienst').' '.($delivery->tracking_number ?: '')));
        }

        if ($delivery->tracking_url) {
            $message->line('Tracking-Link: '.$delivery->tracking_url);
        }

        if ($delivery->notes) {
            $message->line('Hinweis: '.$delivery->notes);
        }

        return $message->action('Outfit-Abo ansehen', route('auth.outfit-subscriptions.index'));
    }

    private function subject(?string $status): string
    {
        return match ($status) {
            'preparing' => 'Deine Outfit-Lieferung wird vorbereitet',
            'shipped' => 'Deine Outfit-Lieferung wurde versendet',
            'delivered' => 'Deine Outfit-Lieferung wurde geliefert',
            'cancelled' => 'Deine Outfit-Lieferung wurde storniert',
            default => 'Deine Outfit-Lieferung wurde geplant',
        };
    }

    private function body(?string $status): string
    {
        return match ($status) {
            'preparing' => 'Deine Sportkleidung-Box wird gerade vorbereitet.',
            'shipped' => 'Deine Sportkleidung-Box wurde versendet.',
            'delivered' => 'Deine Sportkleidung-Box wurde als geliefert markiert.',
            'cancelled' => 'Deine Sportkleidung-Box wurde storniert.',
            default => 'Deine nächste Sportkleidung-Box wurde geplant.',
        };
    }

    private function date($value): string
    {
        return $value ? $value->format('d.m.Y') : 'noch nicht gesetzt';
    }
}
