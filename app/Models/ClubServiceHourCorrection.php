<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubServiceHourCorrection extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'minutes_delta' => 'integer',
            'previous_total_minutes' => 'integer',
            'corrected_total_minutes' => 'integer',
        ];
    }
}
