<?php

namespace App\Services;

use App\Models\ClubSepaNotice;
use App\Support\TransactionalMail;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class ClubSepaNoticeMailer
{
    public function transport(): array
    {
        $transport = app(TransactionalMail::class)->transportFor('billing');
        $driver = config('mail.mailers.'.$transport['mailer'].'.transport');
        // Log/array/null transports and implicit fallback chains are not proof of dispatch.
        abort_unless(in_array($driver, ['smtp', 'ses', 'ses-v2', 'mailgun', 'postmark', 'resend'], true), 422, __('sepa.mail_transport'));

        return $transport;
    }

    public function send(ClubSepaNotice $notice): string
    {
        $transport = $this->transport();
        $content = $notice->content;
        $sent = Mail::mailer($transport['mailer'])->raw($content['body'], function ($message) use ($content, $transport) {
            $message->to($content['email'], $content['name'])->subject($content['subject']);
            if ($transport['address']) {
                $message->from($transport['address'], $transport['name']);
            }
        });
        if (! $sent) {
            throw new RuntimeException('SEPA notice was not accepted by the mail transport.');
        }

        return $sent->getMessageId();
    }
}
