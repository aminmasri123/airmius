<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\User;
use App\Services\Ai\AiProviderTokenStatusService;
use App\Support\AppNotification;
use Illuminate\Console\Command;

class CheckAiProviderTokens extends Command
{
    protected $signature = 'airmius:check-ai-provider-tokens {--dry-run : Nur anzeigen, keine Benachrichtigungen senden}';

    protected $description = 'Prüft KI-Anbieter-Tokens und benachrichtigt System-Admins bei Ablauf oder fehlender Konfiguration.';

    public function handle(AiProviderTokenStatusService $tokens): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $alerts = $tokens->alerts();

        if ($alerts === []) {
            $this->info('Alle KI-Anbieter-Tokens sind aktuell unauffällig.');

            return self::SUCCESS;
        }

        $this->table(['Anbieter', 'Status', 'Ablauf', 'Hinweis'], collect($alerts)->map(fn (array $alert) => [
            $alert['label'],
            $alert['status_label'],
            $alert['expires_at_human'] ?? '-',
            $alert['message'],
        ])->all());

        if ($dryRun) {
            return self::SUCCESS;
        }

        $admins = User::permission('system.manage')->get(['id']);
        $sent = 0;

        foreach ($alerts as $alert) {
            foreach ($admins as $admin) {
                if ($this->recentNotificationExists((int) $admin->id, $alert)) {
                    continue;
                }

                AppNotification::send($admin, $this->notificationType($alert), [
                    'title' => $alert['severity'] === 'danger'
                        ? 'KI-Anbieter benötigt Aufmerksamkeit'
                        : 'KI-Token läuft bald ab',
                    'body' => "{$alert['label']}: {$alert['message']}",
                    'provider' => $alert['key'],
                    'status' => $alert['status'],
                    'expires_at' => $alert['expires_at'],
                    'url' => route('admin.settings.index'),
                ]);

                $sent++;
            }
        }

        $this->info("KI-Token-Prüfung abgeschlossen. {$sent} Benachrichtigung(en) gesendet.");

        return self::SUCCESS;
    }

    private function notificationType(array $alert): string
    {
        return $alert['severity'] === 'danger'
            ? 'admin.ai_token.problem'
            : 'admin.ai_token.expiring';
    }

    private function recentNotificationExists(int $userId, array $alert): bool
    {
        return Notification::query()
            ->where('user_id', $userId)
            ->where('type', $this->notificationType($alert))
            ->where('data->provider', $alert['key'])
            ->where('data->status', $alert['status'])
            ->where('created_at', '>=', now()->subDay())
            ->exists();
    }
}
