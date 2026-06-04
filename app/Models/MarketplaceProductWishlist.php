<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceProductWishlist extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'marketplace_product_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(MarketplaceProduct::class, 'marketplace_product_id');
    }
}
