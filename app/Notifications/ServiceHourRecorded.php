<?php

namespace App\Notifications;

use App\Support\LocalizedMail;
use App\Support\TransactionalMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Lang;

class ServiceHourRecorded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $clubName,
        private readonly string $activity,
        private readonly string $servedOn,
        private readonly int $minutes,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = LocalizedMail::for($notifiable);
        $date = $mail->date(Carbon::parse($this->servedOn));
        $subject = Lang::get('organization.notifications.service_hour_recorded_title', [], $mail->locale);
        $body = Lang::get('organization.notifications.service_hour_recorded_body', [
            'club' => $this->clubName,
            'activity' => $this->activity,
            'date' => $date,
            'minutes' => $this->minutes,
        ], $mail->locale);

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting($mail->greeting($notifiable))
            ->line($body);

        return app(TransactionalMail::class)->applyToMessage($message, 'system');
    }
}
