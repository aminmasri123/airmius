<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceProductReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'marketplace_product_id',
        'user_id',
        'commerce_order_id',
        'rating',
        'title',
        'body',
        'status',
        'verified_purchase',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'verified_purchase' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(MarketplaceProduct::class, 'marketplace_product_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(CommerceOrder::class, 'commerce_order_id');
    }
}
