<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'club_id',
        'team_id',
        'title',
        'description',
        'goals',
        'phases',
        'exercises',
        'materials',
        'duration_minutes',
        'status',
        'revision',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'goals' => 'array',
            'phases' => 'array',
            'exercises' => 'array',
            'materials' => 'array',
            'revision' => 'integer',
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

    public function versions()
    {
        return $this->hasMany(TrainingSessionVersion::class)->orderByDesc('revision');
    }
}
