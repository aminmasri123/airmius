<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkillEndorsement extends Model
{
    protected $fillable = [
        'user_sport_skill_id',
        'endorser_id',
        'relationship',
        'level',
        'comment',
    ];

    public function userSkill()
    {
        return $this->belongsTo(UserSportSkill::class, 'user_sport_skill_id');
    }

    public function endorser()
    {
        return $this->belongsTo(User::class, 'endorser_id');
    }
}
