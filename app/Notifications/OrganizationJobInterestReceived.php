<?php

namespace App\Notifications;

use App\Models\OrganizationJobInterest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationJobInterestReceived extends Notification
{
    use Queueable;

    public function __construct(private OrganizationJobInterest $interest) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $job = $this->interest->job;
        $club = $job->club;

        $message = (new MailMessage)
            ->subject('Neue Interessenmeldung: '.$job->title)
            ->greeting('Hallo,')
            ->line('es gibt eine neue Interessenmeldung für "'.$job->title.'" bei '.$club->name.'.')
            ->line('Name: '.$this->interest->name)
            ->line('E-Mail: '.$this->interest->email);

        if ($this->interest->phone) {
            $message->line('Telefon: '.$this->interest->phone);
        }

        if ($this->interest->message) {
            $message->line('Nachricht: '.$this->interest->message);
        }

        return $message->action('Jobseite ?ffnen', route('guest.jobs'));
    }
}
