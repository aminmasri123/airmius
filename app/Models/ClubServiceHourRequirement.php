<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubServiceHourRequirement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'required_minutes' => 'integer',
            'replacement_rate_cents' => 'integer',
            'locked' => 'boolean',
            'locked_at' => 'datetime',
        ];
    }

    public function period()
    {
        return $this->belongsTo(ClubYearPeriod::class, 'club_year_period_id');
    }

    public function roleDefinition()
    {
        return $this->belongsTo(ClubRoleDefinition::class, 'club_role_definition_id');
    }
}
