<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\GuardianConsentRequested;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class GuardianConsentNotifier
{
    public static function send(User $minor, string $guardianEmail): void
    {
        $guardianEmail = mb_strtolower(trim($guardianEmail));
        $guardian = User::query()
            ->whereRaw('LOWER(email) = ?', [$guardianEmail])
            ->first();

        try {
            if ($guardian) {
                $guardian->notify(new GuardianConsentRequested($minor));

                AppNotification::send($guardian, 'guardian.consent_requested', [
                    'title' => 'Zustimmung für Kind erforderlich',
                    'body' => $minor->name.' hat ein Airmius-Konto erstellt und bittet um deine Zustimmung.',
                    'minor_id' => $minor->id,
                    'minor_name' => $minor->name,
                    'url' => route('guardian-consent.show', $minor->guardian_consent_token),
                ]);

                return;
            }

            Notification::send(
                Notification::route('mail', $guardianEmail),
                new GuardianConsentRequested($minor)
            );
        } catch (\Throwable $exception) {
            Log::warning('Guardian consent notification could not be sent.', [
                'user_id' => $minor->id,
                'guardian_email' => $guardianEmail,
                'guardian_user_id' => $guardian?->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
