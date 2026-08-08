<?php

namespace App\Notifications;

use App\Support\SupportedLocale;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

class NotificationDigest extends Notification
{
    use Queueable;

    /**
     * @param  array<int, array{title:string,body:string,url:?string}>  $items
     * @param  array{mailer?:string,address?:string,name?:string}  $transport
     */
    public function __construct(
        private array $items,
        private array $transport = [],
        private string $digestLocale = 'de',
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $copy = $this->copy();
        $message = (new MailMessage)
            ->subject($copy['subject'])
            ->greeting($copy['greeting'])
            ->line($copy['intro']);

        foreach ($this->items as $item) {
            $title = trim((string) ($item['title'] ?? '')) ?: $copy['fallback_title'];
            $body = trim((string) ($item['body'] ?? ''));
            $line = '• '.$title.($body !== '' ? ': '.$body : '');
            $message->line($line);
        }

        $message->line($copy['outro']);
        $message->action($copy['action'], route('login'));

        if (! empty($this->transport['mailer'])) {
            $message->mailer($this->transport['mailer']);
        }
        if (! empty($this->transport['address'])) {
            $message->from($this->transport['address'], $this->transport['name'] ?? config('mail.from.name'));
        }

        return $message;
    }

    private function copy(): array
    {
        $locale = SupportedLocale::normalize($this->digestLocale) ?? SupportedLocale::DEFAULT;

        return collect(['subject', 'greeting', 'intro', 'outro', 'action', 'fallback_title'])
            ->mapWithKeys(fn (string $key) => [
                $key => Lang::get('platform.notification_digest.'.$key, [], $locale),
            ])
            ->all();
    }
}
