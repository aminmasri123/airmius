<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EventCommerceAssortment extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'commerce_price_list_id',
        'marketplace_product_id',
        'sort_order',
        'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_featured' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $assortment): void {
            $event = Event::query()->find($assortment->event_id);
            $priceList = CommercePriceList::query()->find($assortment->commerce_price_list_id);
            $product = MarketplaceProduct::query()->find($assortment->marketplace_product_id);

            if (! $event || ! $priceList || ! $product) {
                return;
            }

            $clubId = (int) $event->club_id;
            if ($clubId <= 0 || $clubId !== (int) $priceList->club_id || $clubId !== (int) $product->club_id) {
                throw ValidationException::withMessages([
                    'club_id' => __('validation.exists', ['attribute' => 'club_id']),
                ]);
            }
        });
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function priceList()
    {
        return $this->belongsTo(CommercePriceList::class, 'commerce_price_list_id');
    }

    public function product()
    {
        return $this->belongsTo(MarketplaceProduct::class, 'marketplace_product_id');
    }

}
