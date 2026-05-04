<?php

namespace App\Console\Commands;

use App\Models\OutfitDelivery;
use App\Models\OutfitSubscription;
use App\Support\AppNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PrepareOutfitDeliveries extends Command
{
    protected $signature = 'airmius:prepare-outfit-deliveries
        {--date= : Stichtag im Format YYYY-MM-DD, Standard ist heute}';

    protected $description = 'Plant faellige monatliche Sportkleidung-Abo-Lieferungen.';

    public function handle(): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->endOfDay()
            : now()->endOfDay();
        $created = 0;

        OutfitSubscription::query()
            ->with(['user:id,name,email', 'plan:id,name'])
            ->where('status', 'active')
            ->whereNotNull('next_delivery_at')
            ->where('next_delivery_at', '<=', $date)
            ->orderBy('id')
            ->cursor()
            ->each(function (OutfitSubscription $subscription) use (&$created) {
                $deliveryMonth = $subscription->next_delivery_at->copy()->startOfMonth();

                $alreadyPlanned = $subscription->deliveries()
                    ->whereDate('delivery_month', $deliveryMonth->toDateString())
                    ->exists();

                if (! $alreadyPlanned) {
                    OutfitDelivery::query()->create([
                        'outfit_subscription_id' => $subscription->id,
                        'status' => 'planned',
                        'delivery_month' => $deliveryMonth,
                        'items' => [],
                        'notes' => 'Automatisch geplante Monatslieferung.',
                    ]);

                    AppNotification::send($subscription->user_id, 'outfit.delivery.planned', [
                        'title' => 'Outfit-Lieferung geplant',
                        'message' => 'Deine naechste Sportkleidung-Box wird vorbereitet.',
                        'plan' => $subscription->plan?->name,
                        'url' => route('auth.outfit-subscriptions.index'),
                    ]);

                    $created++;
                }

                $subscription->update([
                    'next_delivery_at' => $subscription->next_delivery_at->copy()->addMonthNoOverflow()->startOfDay(),
                    'current_period_ends_at' => $subscription->current_period_ends_at
                        ? $subscription->current_period_ends_at->copy()->addMonthNoOverflow()
                        : now()->addMonthNoOverflow(),
                ]);
            });

        $this->info("{$created} Outfit-Lieferungen geplant.");

        return self::SUCCESS;
    }
}
