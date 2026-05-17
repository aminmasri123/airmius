<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommerceStockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'marketplace_product_id',
        'commerce_warehouse_id',
        'commerce_order_id',
        'commerce_return_request_id',
        'type',
        'quantity_delta',
        'stock_after',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'integer',
            'stock_after' => 'integer',
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
}
