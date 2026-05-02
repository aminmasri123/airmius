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
        'due_date',
        'issued_at',
        'paid_at',
        'reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'datetime',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
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
}
