<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommerceReturnRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'commerce_order_id',
        'commerce_order_item_id',
        'user_id',
        'guest_email',
        'status',
        'reason',
        'resolution_note',
        'quantity',
        'requested_amount_cents',
        'approved_amount_cents',
        'currency',
        'requested_at',
        'approved_at',
        'rejected_at',
        'received_at',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'requested_amount_cents' => 'integer',
            'approved_amount_cents' => 'integer',
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'received_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(CommerceOrder::class, 'commerce_order_id');
    }

    public function item()
    {
        return $this->belongsTo(CommerceOrderItem::class, 'commerce_order_item_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
