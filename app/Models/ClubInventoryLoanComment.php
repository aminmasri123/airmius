<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubInventoryLoanComment extends Model
{
    protected $fillable = ['club_inventory_loan_id', 'user_id', 'message'];

    public function loan()
    {
        return $this->belongsTo(ClubInventoryLoan::class, 'club_inventory_loan_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
