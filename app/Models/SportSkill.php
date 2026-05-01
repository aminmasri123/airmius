<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SportSkill extends Model
{
    protected $fillable = [
        'sport_id',
        'key',
        'name',
        'description',
        'sort_order',
    ];

    public function sport()
    {
        return $this->belongsTo(Sport::class);
    }

    public function userSkills()
    {
        return $this->hasMany(UserSportSkill::class);
    }
}
