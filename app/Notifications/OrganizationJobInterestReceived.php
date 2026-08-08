<?php

namespace App\Notifications;

use App\Models\OrganizationJobInterest;
use App\Support\SupportedLocale;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

class OrganizationJobInterestReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private OrganizationJobInterest $interest,
        private ?string $requestLocale = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $job = $this->interest->job;
        $club = $job->club;
        $locale = SupportedLocale::normalize(data_get($notifiable, 'language'))
            ?? SupportedLocale::normalize($this->requestLocale)
            ?? SupportedLocale::DEFAULT;
        $translate = fn (string $key, array $replace = []): string => Lang::get($key, $replace, $locale);

        $message = (new MailMessage)
            ->subject($translate('recruiting.mail.subject', ['title' => $job->title]))
            ->greeting($translate('recruiting.mail.greeting'))
            ->line($translate('recruiting.mail.intro', ['title' => $job->title, 'club' => $club->name]))
            ->line($translate('recruiting.mail.name', ['name' => $this->interest->name]))
            ->line($translate('recruiting.mail.email', ['email' => $this->interest->email]));

        if ($this->interest->phone) {
            $message->line($translate('recruiting.mail.phone', ['phone' => $this->interest->phone]));
        }

        if ($this->interest->message) {
            $message->line($translate('recruiting.mail.message', ['message' => $this->interest->message]));
        }

        return $message->action($translate('recruiting.mail.action'), route('guest.jobs'));
    }
}
