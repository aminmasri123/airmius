<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingExercise extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'club_id',
        'team_id',
        'name',
        'sport_type',
        'description',
        'instructions',
        'equipment',
        'muscle_groups',
        'difficulty',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'equipment' => 'array',
            'muscle_groups' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function planItems()
    {
        return $this->hasMany(TrainingPlanItem::class, 'source_exercise_id');
    }
}
