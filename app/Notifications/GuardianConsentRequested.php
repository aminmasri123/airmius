<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

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
        $content = EmailTemplate::content('guardian_consent_requested', [
            'minor_name' => $this->minor->name,
        ]);

        $token = $this->minor->guardian_consent_token;

        return (new MailMessage)
            ->subject($content['subject'])
            ->markdown('emails.guardian-consent-requested', [
                'greeting' => $content['greeting'],
                'body' => $content['body'],
                'approveUrl' => URL::signedRoute('guardian-consent.approve-direct', $token),
                'rejectUrl' => URL::signedRoute('guardian-consent.reject-direct', $token),
                'reviewUrl' => route('guardian-consent.show', $token),
            ]);
    }
}
