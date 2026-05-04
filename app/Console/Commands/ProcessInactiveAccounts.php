<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\InactiveAccountNotice;
use App\Services\UserPrivacyRetentionService;
use Illuminate\Console\Command;

class ProcessInactiveAccounts extends Command
{
    protected $signature = 'airmius:process-inactive-accounts {--dry-run : Nur anzeigen, keine Aenderungen speichern}';

    protected $description = 'Sendet Inaktivitaetswarnungen und anonymisiert dauerhaft inaktive Konten DSGVO-konform.';

    public function handle(UserPrivacyRetentionService $retentionService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $now = now();
        $stats = [
            'first' => 0,
            'second' => 0,
            'scheduled' => 0,
            'anonymized' => 0,
        ];

        User::query()
            ->where('privacy_status', '!=', 'anonymized')
            ->whereNotNull('deletion_scheduled_at')
            ->where('deletion_scheduled_at', '<=', $now)
            ->orderBy('id')
            ->chunkById(50, function ($users) use ($retentionService, $dryRun, &$stats) {
                foreach ($users as $user) {
                    if ($this->isPrivileged($user)) {
                        continue;
                    }

                    $stats['anonymized']++;

                    if (! $dryRun) {
                        $retentionService->anonymize($user);
                    }
                }
            });

        User::query()
            ->where('privacy_status', '!=', 'anonymized')
            ->whereNull('deletion_scheduled_at')
            ->where(function ($query) use ($now) {
                $query->where('last_seen_at', '<=', $now->copy()->subMonthsNoOverflow(24))
                    ->orWhere(function ($fallback) use ($now) {
                        $fallback->whereNull('last_seen_at')
                            ->where('updated_at', '<=', $now->copy()->subMonthsNoOverflow(24));
                    });
            })
            ->orderBy('id')
            ->chunkById(50, function ($users) use ($dryRun, &$stats) {
                foreach ($users as $user) {
                    if ($this->isPrivileged($user)) {
                        continue;
                    }

                    $scheduledAt = now()->addDays(30);
                    $stats['scheduled']++;

                    if (! $dryRun) {
                        $user->forceFill([
                            'privacy_status' => 'scheduled_for_anonymization',
                            'deletion_scheduled_at' => $scheduledAt,
                        ])->save();

                        $user->notify(new InactiveAccountNotice('scheduled', $scheduledAt->format('d.m.Y')));
                    }
                }
            });

        $this->sendWarning('second', 18, 'inactivity_second_warning_sent_at', $dryRun, $stats);
        $this->sendWarning('first', 12, 'inactivity_first_warning_sent_at', $dryRun, $stats);

        $this->info('Inaktivitaetslauf abgeschlossen.');
        $this->line('Erste Warnungen: '.$stats['first']);
        $this->line('Zweite Warnungen: '.$stats['second']);
        $this->line('Zur Anonymisierung vorgemerkt: '.$stats['scheduled']);
        $this->line('Anonymisiert: '.$stats['anonymized']);

        return self::SUCCESS;
    }

    private function sendWarning(string $stage, int $months, string $column, bool $dryRun, array &$stats): void
    {
        $now = now();

        User::query()
            ->where('privacy_status', '!=', 'anonymized')
            ->whereNull('deletion_scheduled_at')
            ->whereNull($column)
            ->where(function ($query) use ($now, $months) {
                $query->where('last_seen_at', '<=', $now->copy()->subMonthsNoOverflow($months))
                    ->orWhere(function ($fallback) use ($now, $months) {
                        $fallback->whereNull('last_seen_at')
                            ->where('updated_at', '<=', $now->copy()->subMonthsNoOverflow($months));
                    });
            })
            ->orderBy('id')
            ->chunkById(50, function ($users) use ($stage, $column, $dryRun, &$stats) {
                foreach ($users as $user) {
                    if ($this->isPrivileged($user)) {
                        continue;
                    }

                    $stats[$stage]++;

                    if (! $dryRun) {
                        $user->forceFill([
                            'privacy_status' => 'inactive_warning_sent',
                            $column => now(),
                        ])->save();

                        $user->notify(new InactiveAccountNotice($stage));
                    }
                }
            });
    }

    private function isPrivileged(User $user): bool
    {
        return $user->can('system.manage') || $user->hasRole('super_admin');
    }
}
