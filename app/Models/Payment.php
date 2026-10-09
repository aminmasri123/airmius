<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'user_id',
        'club_external_member_id',
        'invoice_id',
        'purpose',
        'amount',
        'status',
        'method',
        'reference',
        'receipt_number',
        'donation_number',
        'donation_type',
        'donation_restriction',
        'donation_campaign',
        'donor_type',
        'donor_snapshot',
        'club_business_partner_id',
        'sponsor_id',
        'paid_at',
        'notes',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'donor_snapshot' => 'array',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function externalMember()
    {
        return $this->belongsTo(ClubExternalMember::class, 'club_external_member_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function bankTransactions()
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function bookingReceipts()
    {
        return $this->hasMany(PaymentBookingReceipt::class);
    }
}
