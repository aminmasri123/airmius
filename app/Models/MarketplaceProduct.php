<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketplaceProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'club_id',
        'title',
        'description',
        'image_url',
        'category',
        'sku',
        'is_shippable',
        'manages_stock',
        'stock_quantity',
        'low_stock_threshold',
        'tax_class',
        'return_policy_type',
        'return_window_days',
        'price_cents',
        'currency',
        'status',
        'moderation_status',
        'rejection_reason',
        'commission_percent',
        'payout_status',
    ];

    protected function casts(): array
    {
        return [
            'is_shippable' => 'boolean',
            'manages_stock' => 'boolean',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'return_window_days' => 'integer',
            'price_cents' => 'integer',
            'commission_percent' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(CommerceStockMovement::class);
    }
}
