<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'target_actor',
        'name',
        'description',
        'monthly_price_cents',
        'yearly_price_cents',
        'currency',
        'member_limit',
        'team_limit',
        'storage_gb',
        'features',
        'cta_label',
        'badge',
        'sort_order',
        'is_public',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function clubSubscriptions()
    {
        return $this->hasMany(ClubSubscription::class);
    }

    public function userSubscriptions()
    {
        return $this->hasMany(UserSubscription::class);
    }

    public static function free(): ?self
    {
        return static::query()->where('slug', 'free')->first();
    }
}
