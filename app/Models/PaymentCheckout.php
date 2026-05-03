<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentCheckout extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'club_id',
        'subscription_plan_id',
        'subscription_coupon_id',
        'provider',
        'billing_interval',
        'original_amount_cents',
        'discount_cents',
        'amount_cents',
        'currency',
        'status',
        'provider_checkout_id',
        'provider_subscription_id',
        'provider_customer_id',
        'payment_reference',
        'due_at',
        'checkout_url',
        'payload',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
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

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function invoice()
    {
        return $this->hasOne(SubscriptionInvoice::class);
    }

    public function coupon()
    {
        return $this->belongsTo(SubscriptionCoupon::class, 'subscription_coupon_id');
    }
}
