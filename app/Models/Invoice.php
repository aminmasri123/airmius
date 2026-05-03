<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'user_id',
        'number',
        'title',
        'description',
        'amount',
        'status',
        'source',
        'billing_period_start',
        'billing_period_end',
        'due_date',
        'issued_at',
        'paid_at',
        'reminder_sent_at',
        'due_soon_notified_at',
        'sepa_exported_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'billing_period_start' => 'date',
            'billing_period_end' => 'date',
            'due_date' => 'datetime',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'due_soon_notified_at' => 'datetime',
            'sepa_exported_at' => 'datetime',
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

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function bankTransactions()
    {
        return $this->hasMany(BankTransaction::class);
    }
}
