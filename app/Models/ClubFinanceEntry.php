<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubFinanceEntry extends Model
{
    use HasFactory;

    public const TYPES = ['income', 'expense'];
    public const ACCOUNTS = ['cash', 'bank'];

    protected $fillable = [
        'club_id',
        'user_id',
        'receipt_file_id',
        'type',
        'account',
        'category',
        'title',
        'amount',
        'booked_on',
        'reference',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'booked_on' => 'date',
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

    public function receiptFile()
    {
        return $this->belongsTo(File::class, 'receipt_file_id');
    }
}
