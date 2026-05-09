<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutfitSubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'sponsor_id',
        'name',
        'slug',
        'description',
        'contract_title',
        'contract_terms',
        'minimum_term_months',
        'pause_allowed_after_months',
        'cancellation_notice_days',
        'monthly_price_cents',
        'sponsor_discount_cents',
        'currency',
        'target_gender',
        'sizes',
        'sports',
        'items_per_box',
        'branding_type',
        'sort_order',
        'is_public',
        'is_active',
        'paypal_product_id',
        'paypal_plan_id',
        'paypal_plan_signature',
        'paypal_payload',
    ];

    protected function casts(): array
    {
        return [
            'sizes' => 'array',
            'sports' => 'array',
            'contract_terms' => 'array',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
            'paypal_payload' => 'array',
        ];
    }

    public function sponsor()
    {
        return $this->belongsTo(Sponsor::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(OutfitSubscription::class);
    }

    public function effectiveMonthlyPriceCents(): int
    {
        return max(0, (int) $this->monthly_price_cents - (int) $this->sponsor_discount_cents);
    }
}
