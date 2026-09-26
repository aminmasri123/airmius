<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingPlanAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_plan_id',
        'user_id',
        'team_id',
        'club_training_group_id',
        'permission',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    public function plan()
    {
        return $this->belongsTo(TrainingPlan::class, 'training_plan_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function trainingGroup()
    {
        return $this->belongsTo(ClubTrainingGroup::class, 'club_training_group_id');
    }
}
