<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceRefund extends Model
{
    use HasFactory;

    protected $fillable = [
        'commerce_order_id',
        'commerce_return_request_id',
        'requested_by',
        'idempotency_key',
        'amount_cents',
        'currency',
        'provider',
        'provider_refund_id',
        'status',
        'reason',
        'failure_message',
        'payout_impact_cents',
        'payout_impact_status',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'payout_impact_cents' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(CommerceOrder::class, 'commerce_order_id');
    }

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(CommerceReturnRequest::class, 'commerce_return_request_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
