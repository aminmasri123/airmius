<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\User;
use App\Notifications\NotificationDigest;
use App\Support\TransactionalMail;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SendNotificationDigests extends Command
{
    protected $signature = 'airmius:send-notification-digests
        {--hours=24 : Zeitraum ungelesener Benachrichtigungen}
        {--dry-run : Nur Empfänger und Anzahl anzeigen, nichts senden}';

    protected $description = 'Sendet lokalisierte tägliche E-Mail-Zusammenfassungen ungelesener Benachrichtigungen.';

    public function handle(TransactionalMail $mail): int
    {
        $hours = max(1, min(168, (int) $this->option('hours')));
        $cutoff = now()->subHours($hours);
        $dryRun = (bool) $this->option('dry-run');
        $stats = ['users' => 0, 'eligible' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0, 'notifications' => 0];

        User::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->where(function ($query) {
                $query->whereNull('privacy_status')->orWhere('privacy_status', '!=', 'anonymized');
            })
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($mail, $cutoff, $hours, $dryRun, &$stats) {
                foreach ($users as $user) {
                    $stats['users']++;
                    if (! $this->emailDigestEnabled($user)) {
                        $stats['skipped']++;
                        continue;
                    }

                    $notifications = Notification::query()
                        ->where('user_id', $user->id)
                        ->where('read', false)
                        ->whereNull('email_digest_sent_at')
                        ->where('created_at', '>=', $cutoff)
                        ->latest('created_at')
                        ->limit(20)
                        ->get();

                    if ($notifications->isEmpty()) {
                        continue;
                    }

                    $stats['eligible']++;
                    $stats['notifications'] += $notifications->count();
                    $items = $notifications->map(fn (Notification $notification) => [
                        'title' => Str::limit(trim((string) data_get($notification->data, 'title', '')), 140, '…'),
                        'body' => Str::limit(trim((string) data_get($notification->data, 'body', data_get($notification->data, 'message', ''))), 240, '…'),
                        'url' => data_get($notification->data, 'url'),
                    ])->values()->all();

                    if ($dryRun) {
                        $this->line($user->email.' — '.$notifications->count().' Benachrichtigungen');
                        continue;
                    }

                    $dedupeKey = 'notification-digest:'.$user->id.':'.now()->toDateString();
                    $sent = $mail->notifyWithFallback(
                        $user,
                        fn (array $transport) => new NotificationDigest($items, $transport, (string) ($user->language ?: 'de')),
                        'system',
                        null,
                        $dedupeKey,
                        86400,
                        [
                            'mail_type' => 'notification.digest',
                            'notification_count' => $notifications->count(),
                            'hours' => $hours,
                            'user_id' => $user->id,
                        ],
                    );

                    if (! $sent) {
                        $stats['failed']++;
                        continue;
                    }

                    Notification::query()
                        ->whereIn('id', $notifications->modelKeys())
                        ->whereNull('email_digest_sent_at')
                        ->update(['email_digest_sent_at' => now()]);
                    $stats['sent']++;
                }
            });

        $this->info('Benachrichtigungs-Digest abgeschlossen.');
        $this->line('Geprüfte Nutzer: '.$stats['users']);
        $this->line('Empfänger: '.$stats['eligible']);
        $this->line('Versendet: '.$stats['sent']);
        $this->line('Ungelesene Einträge: '.$stats['notifications']);
        $this->line('Übersprungen: '.$stats['skipped']);
        $this->line('Fehlgeschlagen: '.$stats['failed']);

        return self::SUCCESS;
    }

    private function emailDigestEnabled(User $user): bool
    {
        $channels = $user->notification_channels;

        return ! is_array($channels)
            || ! array_key_exists('email', $channels)
            || (bool) $channels['email'];
    }
}
