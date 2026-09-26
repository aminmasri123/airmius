<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubBusinessPartner extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'type',
        'name',
        'number',
        'tax_number',
        'vat_id',
        'iban',
        'bic',
        'contact',
        'metadata',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['contact' => 'array', 'metadata' => 'array', 'is_active' => 'boolean'];
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
