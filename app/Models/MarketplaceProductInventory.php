<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketplaceProductInventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'marketplace_product_id',
        'commerce_warehouse_id',
        'country_code',
        'stock_quantity',
        'reserved_quantity',
        'low_stock_threshold',
        'lead_time_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'stock_quantity' => 'integer',
            'reserved_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'lead_time_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function product()
    {
        return $this->belongsTo(MarketplaceProduct::class, 'marketplace_product_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(CommerceWarehouse::class, 'commerce_warehouse_id');
    }

    public function scopeAvailableForCountry(Builder $query, string $country): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('country_code', strtoupper($country))
            ->whereColumn('stock_quantity', '>', 'reserved_quantity');
    }

    public function availableQuantity(): int
    {
        return max(0, (int) $this->stock_quantity - (int) $this->reserved_quantity);
    }
}
