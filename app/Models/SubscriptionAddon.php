<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionAddon extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'monthly_price_cents',
        'yearly_price_cents',
        'target_actor',
        'features',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function purchases()
    {
        return $this->hasMany(SubscriptionAddonPurchase::class);
    }
}
