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
        'features',
        'product_attributes',
        'attribute_options',
        'variants',
        'image_url',
        'gallery_images',
        'category',
        'offer_type',
        'product_type',
        'sku',
        'is_shippable',
        'manages_stock',
        'stock_quantity',
        'low_stock_threshold',
        'tax_class',
        'return_policy_type',
        'return_window_days',
        'digital_delivery_note',
        'course_outline',
        'learning_goals',
        'coaching_enabled',
        'coach_feedback_instructions',
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
            'features' => 'array',
            'product_attributes' => 'array',
            'attribute_options' => 'array',
            'variants' => 'array',
            'gallery_images' => 'array',
            'course_outline' => 'array',
            'learning_goals' => 'array',
            'coaching_enabled' => 'boolean',
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
