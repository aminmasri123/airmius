<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubMoneyAccount extends Model
{
    protected $fillable = ['club_id', 'team_id', 'name', 'type', 'bank_name', 'account_holder', 'iban', 'bic', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function entries()
    {
        return $this->hasMany(ClubFinanceEntry::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
