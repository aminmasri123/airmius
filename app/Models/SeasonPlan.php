<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeasonPlan extends Model
{
    protected $fillable = ['team_id', 'sport_year_period_id', 'name', 'goals', 'status', 'phases', 'revision', 'updated_by'];

    protected function casts(): array
    {
        return ['phases' => 'array', 'revision' => 'integer'];
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function events()
    {
        return $this->belongsToMany(Event::class, 'season_plan_events');
    }
}
