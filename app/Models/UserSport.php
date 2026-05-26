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
        'training_profile_completed_at',
    ];

    protected function casts(): array
    {
        return [
            'performance_metrics' => 'array',
            'performance_visibility' => 'array',
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
