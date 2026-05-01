<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSportSkill extends Model
{
    protected $fillable = [
        'user_id',
        'sport_id',
        'sport_skill_id',
        'self_level',
        'is_visible',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sport()
    {
        return $this->belongsTo(Sport::class);
    }

    public function skill()
    {
        return $this->belongsTo(SportSkill::class, 'sport_skill_id');
    }

    public function endorsements()
    {
        return $this->hasMany(SkillEndorsement::class);
    }
}
