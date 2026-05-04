<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommerceOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'guest_name',
        'guest_email',
        'access_token',
        'club_id',
        'orderable_type',
        'orderable_id',
        'type',
        'provider',
        'billing_interval',
        'amount_cents',
        'commission_cents',
        'payout_id',
        'payout_status',
        'currency',
        'status',
        'issue_status',
        'issue_note',
        'issue_reported_at',
        'provider_checkout_id',
        'payment_reference',
        'due_at',
        'checkout_url',
        'payload',
        'completed_at',
        'confirmation_email_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'confirmation_email_sent_at' => 'datetime',
            'issue_reported_at' => 'datetime',
        ];
    }

    public function orderable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function payout()
    {
        return $this->belongsTo(MarketplacePayout::class, 'payout_id');
    }
}
