<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubTrainingGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id', 'club_department_id', 'club_location_id', 'sport_year_period_id',
        'name', 'sport_type', 'description', 'is_public',
        'birth_year_from', 'birth_year_to', 'performance_level', 'capacity',
        'waitlist_enabled', 'valid_from', 'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'waitlist_enabled' => 'boolean',
            'valid_from' => 'date:Y-m-d',
            'valid_until' => 'date:Y-m-d',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function department()
    {
        return $this->belongsTo(ClubDepartment::class, 'club_department_id');
    }

    public function location()
    {
        return $this->belongsTo(ClubLocation::class, 'club_location_id');
    }

    public function sportYearPeriod()
    {
        return $this->belongsTo(ClubYearPeriod::class, 'sport_year_period_id');
    }

    public function teams()
    {
        return $this->hasMany(Team::class);
    }
}
