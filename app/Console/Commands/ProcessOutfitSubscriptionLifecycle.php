<?php

namespace App\Console\Commands;

use App\Models\OutfitSubscription;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ProcessOutfitSubscriptionLifecycle extends Command
{
    protected $signature = 'airmius:process-outfit-subscription-lifecycle
        {--date= : Stichtag im Format YYYY-MM-DD, Standard ist heute}';

    protected $description = 'Schließt fällige Outfit-Abo-Kündigungen endgültig ab.';

    public function handle(): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->endOfDay()
            : now()->endOfDay();
        $cancelled = 0;

        OutfitSubscription::query()
            ->where('status', 'cancels_at_period_end')
            ->whereNotNull('current_period_ends_at')
            ->where('current_period_ends_at', '<=', $date)
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) use (&$cancelled) {
                foreach ($subscriptions as $subscription) {
                    $subscription->forceFill([
                        'status' => 'cancelled',
                        'next_delivery_at' => null,
                    ])->save();

                    $cancelled++;
                }
            });

        $this->info("{$cancelled} Outfit-Abos endgültig gekündigt.");

        return self::SUCCESS;
    }
}
