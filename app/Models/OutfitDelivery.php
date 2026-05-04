<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutfitDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'outfit_subscription_id',
        'status',
        'delivery_month',
        'tracking_number',
        'carrier',
        'shipped_at',
        'delivered_at',
        'items',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'delivery_month' => 'date',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'items' => 'array',
        ];
    }

    public function subscription()
    {
        return $this->belongsTo(OutfitSubscription::class, 'outfit_subscription_id');
    }
}
