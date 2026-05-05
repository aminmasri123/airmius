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

    public function countryPrices()
    {
        return $this->hasMany(SubscriptionPlanPrice::class);
    }

    public function priceForCountry(?string $country): array
    {
        $country = strtoupper((string) $country);
        $price = $country !== ''
            ? $this->countryPrices->firstWhere('country_code', $country)
            : null;

        if ($price && ! $price->is_active) {
            return [
                'available' => false,
                'country_code' => $country,
                'currency' => $price->currency,
                'monthly_price_cents' => $price->monthly_price_cents,
                'yearly_price_cents' => $price->yearly_price_cents,
                'localized' => true,
            ];
        }

        return [
            'available' => true,
            'country_code' => $price?->country_code ?: ($country ?: null),
            'currency' => $price?->currency ?: ($this->currency ?: 'EUR'),
            'monthly_price_cents' => $price?->monthly_price_cents ?? $this->monthly_price_cents,
            'yearly_price_cents' => $price?->yearly_price_cents ?? $this->yearly_price_cents,
            'localized' => (bool) $price,
        ];
    }

    public static function free(): ?self
    {
        return static::query()->where('slug', 'free')->first();
    }
}
