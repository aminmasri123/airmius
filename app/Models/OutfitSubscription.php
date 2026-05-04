<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutfitSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'outfit_subscription_plan_id',
        'sponsor_id',
        'status',
        'monthly_price_cents',
        'sponsor_discount_cents',
        'currency',
        'next_delivery_at',
        'current_period_ends_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'next_delivery_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(OutfitSubscriptionPlan::class, 'outfit_subscription_plan_id');
    }

    public function sponsor()
    {
        return $this->belongsTo(Sponsor::class);
    }

    public function deliveries()
    {
        return $this->hasMany(OutfitDelivery::class);
    }
}
