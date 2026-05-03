<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'subscription_plan_id',
        'status',
        'trial_ends_at',
        'current_period_ends_at',
        'renewal_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'renewal_notified_at' => 'datetime',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }
}
