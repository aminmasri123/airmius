<?php

namespace App\Console\Commands;

use App\Services\MobilePushDeliveryService;
use Illuminate\Console\Command;

class DispatchMobilePushDeliveries extends Command
{
    protected $signature = 'airmius:mobile-push-dispatch {--limit=100}';

    protected $description = 'Dispatch queued mobile push deliveries through the configured provider adapter.';

    public function handle(MobilePushDeliveryService $service): int
    {
        $summary = $service->dispatchQueued((int) $this->option('limit'));

        $this->info(sprintf(
            'Processed %d mobile push deliveries: %d sent, %d skipped, %d failed.',
            $summary['processed'],
            $summary['sent'],
            $summary['skipped'],
            $summary['failed'],
        ));

        return self::SUCCESS;
    }
}
