<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSport extends Model
{
    protected $fillable = [
        'user_id',
        'sport_id',
        'status',
        'experience_level',
        'visibility',
        'performance_metrics',
        'performance_visibility',
        'sport_participation',
        'development_goals',
        'sport_results',
        'personal_bests',
        'training_profile_completed_at',
    ];

    protected function casts(): array
    {
        return [
            'performance_metrics' => 'array',
            'performance_visibility' => 'array',
            'sport_participation' => 'array',
            'development_goals' => 'array',
            'sport_results' => 'array',
            'personal_bests' => 'array',
            'training_profile_completed_at' => 'datetime',
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
}
