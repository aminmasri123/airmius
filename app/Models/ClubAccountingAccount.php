<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubAccountingAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'code',
        'name',
        'type',
        'datev_code',
        'is_cash_account',
        'is_bank_account',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_cash_account' => 'boolean',
            'is_bank_account' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function financeEntries()
    {
        return $this->hasMany(ClubFinanceEntry::class);
    }

    public function financeAssignments()
    {
        return $this->hasMany(ClubFinanceAssignment::class);
    }
}
