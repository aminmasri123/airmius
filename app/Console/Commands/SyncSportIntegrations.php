<?php

namespace App\Console\Commands;

use App\Models\ConnectedSportAccount;
use App\Services\SportIntegrationSyncService;
use Illuminate\Console\Command;

class SyncSportIntegrations extends Command
{
    protected $signature = 'airmius:sync-sport-integrations {--provider= : Optionaler Provider, z. B. google_fit oder strava}';

    protected $description = 'Synchronisiert verbundene Sport-Integrationen automatisch.';

    public function handle(SportIntegrationSyncService $syncService): int
    {
        $provider = $this->option('provider');
        $stats = ['synced' => 0, 'failed' => 0];

        ConnectedSportAccount::query()
            ->when($provider, fn ($query) => $query->where('provider', $provider))
            ->whereIn('provider', ['google_fit', 'strava'])
            ->where('status', 'connected')
            ->orderBy('id')
            ->chunkById(50, function ($accounts) use ($syncService, &$stats) {
                foreach ($accounts as $account) {
                    $result = $syncService->sync($account);

                    if ($result['ok'] ?? false) {
                        $stats['synced']++;
                    } else {
                        $stats['failed']++;
                    }
                }
            });

        $this->info("Sport-Sync abgeschlossen. Erfolgreich: {$stats['synced']}, fehlgeschlagen: {$stats['failed']}.");

        return self::SUCCESS;
    }
}
