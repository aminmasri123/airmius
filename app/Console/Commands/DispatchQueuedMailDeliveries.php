<?php

namespace App\Console\Commands;

use App\Jobs\SendQueuedMailDelivery;
use App\Models\MailDelivery;
use Illuminate\Console\Command;

class DispatchQueuedMailDeliveries extends Command
{
    protected $signature = 'airmius:mail-delivery-dispatch {--limit=250}';

    protected $description = 'Dispatch queued mail deliveries with controlled retry metadata.';

    public function handle(): int
    {
        $deliveries = MailDelivery::query()
            ->where('status', 'queued')
            ->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->oldest('queued_at')
            ->limit((int) $this->option('limit'))
            ->get();

        $deliveries->each(fn (MailDelivery $delivery) => SendQueuedMailDelivery::dispatch($delivery->id));

        $this->info(sprintf('Dispatched %d queued mail deliveries.', $deliveries->count()));

        return self::SUCCESS;
    }
}
