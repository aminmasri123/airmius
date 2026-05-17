<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_plan_id',
        'status',
        'cancel_at_period_end',
        'payment_provider',
        'billing_interval',
        'provider_subscription_id',
        'provider_customer_id',
        'trial_ends_at',
        'current_period_ends_at',
        'next_invoice_at',
        'grace_period_ends_at',
        'access_restricted_at',
        'cancels_at',
        'cancelled_at',
        'last_renewed_at',
        'renewal_notified_at',
        'cancellation_email_sent_at',
        'renewal_email_sent_at',
        'payment_issue_email_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'next_invoice_at' => 'datetime',
            'grace_period_ends_at' => 'datetime',
            'access_restricted_at' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'cancels_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_renewed_at' => 'datetime',
            'renewal_notified_at' => 'datetime',
            'cancellation_email_sent_at' => 'datetime',
            'renewal_email_sent_at' => 'datetime',
            'payment_issue_email_sent_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }
}
