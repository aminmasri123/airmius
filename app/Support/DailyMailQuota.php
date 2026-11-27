<?php

namespace App\Support;

use App\Notifications\AccountSecurityNotice;
use App\Notifications\LoginLockoutNotification;
use App\Notifications\LoginSuccessfulNotification;
use App\Notifications\MobilePasswordResetRequested;
use App\Notifications\MobileVerifyEmail;
use App\Notifications\MyCustomResetPassword;
use App\Notifications\TwoFactorChangeCodeRequested;
use App\Notifications\TwoFactorLoginCodeRequested;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class DailyMailQuota
{
    private const SECURITY_MAIL = [
        AccountSecurityNotice::class, LoginLockoutNotification::class, LoginSuccessfulNotification::class,
        MobilePasswordResetRequested::class, MobileVerifyEmail::class, MyCustomResetPassword::class,
        TwoFactorChangeCodeRequested::class, TwoFactorLoginCodeRequested::class, VerifyEmailNotification::class,
    ];

    public function reserve(MessageSending $event): void
    {
        $mailer = $event->data['mailer'] ?? config('mail.default');
        if (in_array(config("mail.mailers.{$mailer}.transport"), ['array', 'log'], true)) {
            return;
        }

        $message = $event->message;
        $count = count($message->getTo()) + count($message->getCc()) + count($message->getBcc());
        $limit = in_array($event->data['__laravel_notification'] ?? null, self::SECURITY_MAIL, true) ? 200 : 150;
        $day = now('UTC')->toDateString();
        $db = DB::connection();
        if ($db->transactionLevel() > 0) {
            // The delivery can outlive a rollback of the surrounding business transaction.
            $name = $db->getName().'_mail_quota';
            config(["database.connections.{$name}" => $db->getConfig()]);
            $db = DB::connection($name);
        }

        $used = $db->transaction(function () use ($db, $day, $count, $limit) {
            $db->table('daily_mail_usage')->insertOrIgnore(['day' => $day, 'recipients' => 0]);
            $usage = $db->table('daily_mail_usage')->where('day', $day);
            if (! (clone $usage)->where('recipients', '<=', $limit - $count)->increment('recipients', $count)) {
                throw new RuntimeException('Tageskontingent für den E-Mail-Versand erreicht.');
            }

            return (int) $usage->value('recipients');
        }, 3);

        if ($used >= 100 && $used - $count < 100) {
            Log::warning('Mindestens 100 E-Mail-Empfänger im heutigen Versandkontingent.', ['day' => $day, 'used' => $used]);
        }
    }
}
