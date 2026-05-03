<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'invoice_id',
        'payment_id',
        'imported_by',
        'transaction_hash',
        'booking_date',
        'amount',
        'currency',
        'debtor_name',
        'debtor_iban',
        'purpose',
        'status',
        'match_confidence',
        'match_reason',
        'raw_data',
    ];

    protected function casts(): array
    {
        return [
            'booking_date' => 'date',
            'amount' => 'decimal:2',
            'match_confidence' => 'integer',
            'raw_data' => 'array',
        ];
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

    public function importer()
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
