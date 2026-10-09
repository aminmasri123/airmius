<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubPaymentAllocation extends Model
{
    protected $fillable = ['club_id', 'payment_id', 'invoice_id', 'amount_cents', 'released_at'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'released_at' => 'datetime'];
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
