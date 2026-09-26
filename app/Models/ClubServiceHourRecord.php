<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubServiceHourRecord extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'served_on' => 'date:Y-m-d',
            'minutes' => 'integer',
            'confirmation_snapshot' => 'array',
            'confirmed_at' => 'datetime',
        ];
    }

    public function corrections()
    {
        return $this->hasMany(ClubServiceHourCorrection::class);
    }

    public function period()
    {
        return $this->belongsTo(ClubYearPeriod::class, 'club_year_period_id');
    }
}
