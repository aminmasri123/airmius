<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionAddonPurchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_addon_id',
        'user_id',
        'club_id',
        'status',
        'current_period_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'current_period_ends_at' => 'datetime',
        ];
    }

    public function addon()
    {
        return $this->belongsTo(SubscriptionAddon::class, 'subscription_addon_id');
    }
}
