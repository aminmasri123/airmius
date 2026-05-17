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
        'tracking_url',
        'carrier',
        'shipped_at',
        'delivered_at',
        'items',
        'notes',
        'issue_type',
        'issue_status',
        'issue_description',
        'issue_requested_resolution',
        'issue_exchange_size',
        'issue_admin_note',
        'return_tracking_number',
        'return_tracking_url',
        'issue_requested_at',
        'issue_resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'delivery_month' => 'date',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'items' => 'array',
            'issue_requested_at' => 'datetime',
            'issue_resolved_at' => 'datetime',
        ];
    }

    public function subscription()
    {
        return $this->belongsTo(OutfitSubscription::class, 'outfit_subscription_id');
    }
}
