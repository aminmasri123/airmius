<?php

namespace App\Services;

use App\Models\Club;
use App\Models\MarketplaceProduct;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ClubShopProductNumberService
{
    public function __construct(private readonly ClubNumberRangeService $numberRanges) {}

    public function create(array $attributes, ?User $actor = null): MarketplaceProduct
    {
        return DB::transaction(function () use ($attributes, $actor) {
            $club = filled($attributes['club_id'] ?? null)
                ? Club::query()->findOrFail((int) $attributes['club_id'])
                : null;
            $allocation = null;
            if ($club && blank($attributes['sku'] ?? null)) {
                $allocation = $this->numberRanges->allocateDefault(
                    $club,
                    'shop_sku',
                    $actor,
                    (string) Str::uuid(),
                    fn (string $number) => ! MarketplaceProduct::query()
                        ->where('club_id', $club->id)
                        ->where('sku', $number)
                        ->exists(),
                );
                $attributes['sku'] = $allocation?->formatted_number;
            }

            $product = MarketplaceProduct::query()->create($attributes);
            if ($allocation) {
                $this->numberRanges->assignTo($allocation, 'shop_sku', $product->id);
            }

            return $product;
        });
    }
}
