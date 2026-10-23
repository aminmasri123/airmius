<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\GuardianConsentRequested;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class GuardianConsentNotifier
{
    public static function send(User $minor, string $guardianEmail): bool
    {
        $guardianEmail = mb_strtolower(trim($guardianEmail));
        $guardian = User::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$guardianEmail])
            ->first();

        if ($guardian) {
            $mailSent = true;

            try {
                $guardian->notify(new GuardianConsentRequested($minor));
            } catch (\Throwable $exception) {
                $mailSent = false;
                self::logFailure($minor, $guardianEmail, $guardian, 'mail', $exception);
            }

            try {
                AppNotification::send($guardian, 'guardian.consent_requested', [
                    'title' => 'Zustimmung für Kind erforderlich',
                    'body' => $minor->name.' hat ein Airmius-Konto erstellt und bittet um deine Zustimmung.',
                    'minor_id' => $minor->id,
                    'minor_name' => $minor->name,
                    'url' => route('guardian-consent.show', $minor->guardian_consent_token),
                ]);
            } catch (\Throwable $exception) {
                self::logFailure($minor, $guardianEmail, $guardian, 'app', $exception);
            }

            return $mailSent;
        }

        try {
            Notification::send(
                Notification::route('mail', $guardianEmail),
                new GuardianConsentRequested($minor)
            );

            return true;
        } catch (\Throwable $exception) {
            self::logFailure($minor, $guardianEmail, null, 'mail', $exception);

            return false;
        }
    }

    private static function logFailure(
        User $minor,
        string $guardianEmail,
        ?User $guardian,
        string $channel,
        \Throwable $exception,
    ): void {
        Log::warning('Guardian consent notification could not be sent.', [
            'user_id' => $minor->id,
            'guardian_email' => $guardianEmail,
            'guardian_user_id' => $guardian?->id,
            'channel' => $channel,
            'exception' => $exception->getMessage(),
        ]);
    }
}
