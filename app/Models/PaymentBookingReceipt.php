<?php

namespace App\Models;

use LogicException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentBookingReceipt extends Model
{
    use HasFactory;

    public const ACTION_RECORDED = 'recorded';
    public const ACTION_CORRECTED = 'corrected';

    protected $fillable = [
        'club_id',
        'invoice_id',
        'payment_id',
        'user_id',
        'club_external_member_id',
        'actor_id',
        'action',
        'claim_status_before',
        'claim_status_after',
        'payment_status',
        'payment_method',
        'amount_cents',
        'currency',
        'receipt_number',
        'reference',
        'payload',
        'previous_hash',
        'hash',
        'booked_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'payload' => 'array',
            'booked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Payment booking receipts are immutable.'));
        static::deleting(fn () => throw new LogicException('Payment booking receipts are immutable.'));
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function externalMember()
    {
        return $this->belongsTo(ClubExternalMember::class, 'club_external_member_id');
    }
}
