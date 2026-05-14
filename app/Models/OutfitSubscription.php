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
        'payment_provider',
        'payment_status',
        'payment_reference',
        'payment_due_at',
        'payment_payload',
        'payment_reminders_sent',
        'last_payment_reminder_sent_at',
        'dunning_level',
        'last_dunning_sent_at',
        'payment_paused_at',
        'payment_paused_reason',
        'accepted_terms_at',
        'accepted_contract_at',
        'contract_version',
        'contract_snapshot',
        'accepted_ip',
        'accepted_user_agent',
        'provider_checkout_id',
        'provider_subscription_id',
        'checkout_url',
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
            'payment_due_at' => 'datetime',
            'payment_payload' => 'array',
            'payment_reminders_sent' => 'integer',
            'last_payment_reminder_sent_at' => 'datetime',
            'dunning_level' => 'integer',
            'last_dunning_sent_at' => 'datetime',
            'payment_paused_at' => 'datetime',
            'accepted_terms_at' => 'datetime',
            'accepted_contract_at' => 'datetime',
            'contract_snapshot' => 'array',
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

    public function latestDelivery()
    {
        return $this->hasOne(OutfitDelivery::class)->latestOfMany();
    }
}
