<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GuardianConsentRequested extends Notification
{
    use Queueable;

    public function __construct(
        public User $minor
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return EmailTemplate::mail('guardian_consent_requested', [
            'minor_name' => $this->minor->name,
        ], route('guardian-consent.show', $this->minor->guardian_consent_token));
    }
}
