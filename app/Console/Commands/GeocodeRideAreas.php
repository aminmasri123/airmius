<?php

namespace App\Console\Commands;

use App\Models\Ride;
use App\Services\RideAddressService;
use Illuminate\Console\Command;

class GeocodeRideAreas extends Command
{
    protected $signature = 'rides:geocode-areas {--limit=100 : Maximum number of existing rides to process} {--after-id=0 : Continue after this ride ID}';

    protected $description = 'Resolve public locality centres for existing carpool rides (never private streets)';

    public function handle(RideAddressService $addresses): int
    {
        $count = 0;
        $lastId = max(0, (int) $this->option('after-id'));
        Ride::query()->where(fn ($q) => $q->whereNull('pickup_area_latitude')->orWhereNull('destination_area_latitude'))
            ->where('id', '>', $lastId)
            ->where('departure_time', '>=', now())->orderBy('id')->limit(max(1, min(1000, (int) $this->option('limit'))))->get()
            ->each(function (Ride $ride) use ($addresses, &$count, &$lastId) {
                $lastId = $ride->id;
                $coordinates = $addresses->coordinates([], $ride);
                // Do not overwrite a locality edited while the provider was responding.
                Ride::whereKey($ride->id)->where('updated_at', $ride->getRawOriginal('updated_at'))->update($coordinates);
                $ride->refresh();
                if ($ride->pickup_area_latitude !== null && $ride->destination_area_latitude !== null) {
                    $count++;
                }
            });
        $this->info("$count rides have both locality centres. Unresolved rides remain available in text search.");
        $this->info("Continue the next batch with --after-id=$lastId");

        return self::SUCCESS;
    }
}
