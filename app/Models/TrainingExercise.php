<?php

namespace App\Models;

use App\Services\Training\TrainingExerciseContentService;
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
        'original_locale',
        'notes',
        'todos',
        'translations',
        'explanation_images',
        'video_url',
        'sport_type',
        'sport_types',
        'category',
        'target_age_group',
        'target_level',
        'description',
        'instructions',
        'equipment',
        'muscle_groups',
        'focus_areas',
        'protected_media',
        'difficulty',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sport_types' => 'array',
            'equipment' => 'array',
            'todos' => 'array',
            'translations' => 'array',
            'explanation_images' => 'array',
            'muscle_groups' => 'array',
            'focus_areas' => 'array',
            'protected_media' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // The shared content contract uses "title" for both library exercises and plan items.
    public function getTitleAttribute(): string
    {
        return $this->name ?? '';
    }

    protected static function booted(): void
    {
        static::updated(function (self $exercise) {
            if ($exercise->wasChanged('explanation_images')) {
                app(TrainingExerciseContentService::class)->releaseUnusedImages($exercise->getOriginal('explanation_images') ?? []);
            }
        });
        static::deleted(fn (self $exercise) => app(TrainingExerciseContentService::class)->releaseUnusedImages($exercise->explanation_images ?? []));
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
