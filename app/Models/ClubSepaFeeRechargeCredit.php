<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubSepaFeeRechargeCredit extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'refund_due_cents' => 'integer',
            'reviewed_at' => 'datetime',
            'refund_booked_on' => 'date',
            'refund_created_entry' => 'boolean',
            'refund_recorded_at' => 'datetime',
        ];
    }

    public function recharge()
    {
        return $this->belongsTo(ClubSepaFeeRecharge::class, 'recharge_id');
    }
}
