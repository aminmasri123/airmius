<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\InactiveAccountNotice;
use App\Services\UserPrivacyRetentionService;
use App\Support\TransactionalMail;
use Illuminate\Console\Command;

class ProcessInactiveAccounts extends Command
{
    protected $signature = 'airmius:process-inactive-accounts {--dry-run : Nur anzeigen, keine änderungen speichern}';

    protected $description = 'Sendet Inaktivitätswarnungen und anonymisiert dauerhaft inaktive Konten DSGVO-konform.';

    public function handle(UserPrivacyRetentionService $retentionService, TransactionalMail $mail): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $now = now();
        $stats = [
            'first' => 0,
            'second' => 0,
            'restricted' => 0,
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
            ->whereRaw('COALESCE(last_login_at, last_seen_at, updated_at, created_at) <= ?', [
                $now->copy()->subMonthsNoOverflow(24)->toDateTimeString(),
            ])
            ->orderBy('id')
            ->chunkById(50, function ($users) use ($dryRun, &$stats, $mail) {
                foreach ($users as $user) {
                    if ($this->isPrivileged($user)) {
                        continue;
                    }

                    $basis = $this->activityBasis($user);
                    $scheduledAt = $basis->copy()->addMonthsNoOverflow(36);
                    $stats['restricted']++;

                    if (! $dryRun) {
                        $user->forceFill([
                            'privacy_status' => 'scheduled_for_anonymization',
                            'profile_visibility' => 'private',
                            'deletion_scheduled_at' => $scheduledAt,
                        ])->save();

                        $this->sendNotice($mail, $user, 'scheduled', $scheduledAt);
                    }
                }
            });

        $this->sendWarning($mail, 'second', 18, 'inactivity_second_warning_sent_at', $dryRun, $stats);
        $this->sendWarning($mail, 'first', 12, 'inactivity_first_warning_sent_at', $dryRun, $stats, 18);

        $this->info('Inaktivitätslauf abgeschlossen.');
        $this->line('Erste Warnungen: '.$stats['first']);
        $this->line('Zweite Warnungen: '.$stats['second']);
        $this->line('Profile eingeschraenkt und zur Anonymisierung vorgemerkt: '.$stats['restricted']);
        $this->line('Anonymisiert: '.$stats['anonymized']);

        return self::SUCCESS;
    }

    private function sendWarning(TransactionalMail $mail, string $stage, int $months, string $column, bool $dryRun, array &$stats, ?int $beforeMonths = null): void
    {
        $now = now();

        User::query()
            ->where('privacy_status', '!=', 'anonymized')
            ->whereNull('deletion_scheduled_at')
            ->whereNull($column)
            ->whereRaw('COALESCE(last_login_at, last_seen_at, updated_at, created_at) <= ?', [
                $now->copy()->subMonthsNoOverflow($months)->toDateTimeString(),
            ])
            ->when($beforeMonths, function ($query) use ($now, $beforeMonths) {
                $query->whereRaw('COALESCE(last_login_at, last_seen_at, updated_at, created_at) > ?', [
                    $now->copy()->subMonthsNoOverflow($beforeMonths)->toDateTimeString(),
                ]);
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

                        $this->sendNotice($mail, $user, $stage);
                    }
                }
            });
    }

    private function sendNotice(TransactionalMail $mail, User $user, string $stage, ?\Carbon\CarbonInterface $scheduledAt = null): bool
    {
        return $mail->notifyWithFallback(
            $user,
            fn (array $transport) => new InactiveAccountNotice(
                $stage,
                $scheduledAt?->format('d.m.Y'),
                $transport['mailer'],
                $transport['address'],
                $transport['name'],
            ),
            'support',
            'billing',
            'inactive-account:'.$stage.':'.$user->id.':'.now()->format('Y-m-d'),
            60,
            [
                'mail_type' => 'inactive_account.'.$stage,
                'user_id' => $user->id,
                'privacy_status' => $user->privacy_status,
                'scheduled_at' => $scheduledAt?->toDateString(),
            ],
        );
    }

    private function activityBasis(User $user): \Carbon\CarbonInterface
    {
        return $user->last_login_at
            ?? $user->last_seen_at
            ?? $user->updated_at
            ?? $user->created_at
            ?? now();
    }

    private function isPrivileged(User $user): bool
    {
        return $user->can('system.manage') || $user->hasRole('super_admin');
    }
}

