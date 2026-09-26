<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubSepaFeeCorrection extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['booked_on' => 'date', 'revision' => 'integer', 'previous_amount_cents' => 'integer', 'amount_cents' => 'integer'];
    }
}
