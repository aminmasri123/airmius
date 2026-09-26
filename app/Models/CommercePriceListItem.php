<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommercePriceListItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'commerce_price_list_id',
        'marketplace_product_id',
        'price_cents',
        'currency',
        'min_quantity',
        'max_quantity',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'min_quantity' => 'integer',
            'max_quantity' => 'integer',
        ];
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
