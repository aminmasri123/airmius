<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommerceCartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'commerce_cart_id',
        'marketplace_product_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function cart()
    {
        return $this->belongsTo(CommerceCart::class, 'commerce_cart_id');
    }

    public function product()
    {
        return $this->belongsTo(MarketplaceProduct::class, 'marketplace_product_id');
    }
}
