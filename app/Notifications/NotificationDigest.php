<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

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
        return match (strtolower(substr($this->digestLocale, 0, 2))) {
            'en' => [
                'subject' => 'Your Airmius notification summary',
                'greeting' => 'Hello,',
                'intro' => 'Here is your summary of unread Airmius notifications from the last day:',
                'outro' => 'Open Airmius to read the full details and manage your notifications.',
                'action' => 'Open Airmius',
                'fallback_title' => 'Notification',
            ],
            'fr' => [
                'subject' => 'Votre résumé de notifications Airmius',
                'greeting' => 'Bonjour,',
                'intro' => 'Voici le résumé de vos notifications Airmius non lues de la dernière journée :',
                'outro' => 'Ouvrez Airmius pour lire les détails et gérer vos notifications.',
                'action' => 'Ouvrir Airmius',
                'fallback_title' => 'Notification',
            ],
            'ar' => [
                'subject' => 'ملخص إشعارات Airmius',
                'greeting' => 'مرحبًا،',
                'intro' => 'إليك ملخص إشعارات Airmius غير المقروءة خلال اليوم الماضي:',
                'outro' => 'افتح Airmius لقراءة التفاصيل وإدارة إشعاراتك.',
                'action' => 'فتح Airmius',
                'fallback_title' => 'إشعار',
            ],
            default => [
                'subject' => 'Deine Airmius-Benachrichtigungsübersicht',
                'greeting' => 'Hallo,',
                'intro' => 'Hier ist deine Übersicht ungelesener Airmius-Benachrichtigungen aus dem letzten Tag:',
                'outro' => 'Öffne Airmius, um die Details zu lesen und deine Benachrichtigungen zu verwalten.',
                'action' => 'Airmius öffnen',
                'fallback_title' => 'Benachrichtigung',
            ],
        };
    }
}
