<?php

namespace App\Models;

use App\Services\Training\TrainingExerciseContentService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingPlanItem extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::updated(function (self $item) {
            if ($item->wasChanged('explanation_images')) {
                app(TrainingExerciseContentService::class)->releaseUnusedImages($item->getOriginal('explanation_images') ?? []);
            }
        });
        static::deleted(fn (self $item) => app(TrainingExerciseContentService::class)->releaseUnusedImages($item->explanation_images ?? []));
    }

    protected $fillable = [
        'training_plan_id',
        'source_exercise_id',
        'training_session_id',
        'sport_route_id',
        'title',
        'original_locale',
        'instructions',
        'notes',
        'translations',
        'explanation_images',
        'sport_type',
        'description',
        'scheduled_at',
        'period_week',
        'period_month',
        'duration_minutes',
        'distance_meters',
        'calories',
        'intensity',
        'image_path',
        'video_url',
        'todos',
        'metrics',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'period_week' => 'integer',
            'period_month' => 'integer',
            'todos' => 'array',
            'translations' => 'array',
            'explanation_images' => 'array',
            'metrics' => 'array',
        ];
    }

    public function plan()
    {
        return $this->belongsTo(TrainingPlan::class, 'training_plan_id');
    }

    public function sourceExercise()
    {
        return $this->belongsTo(TrainingExercise::class, 'source_exercise_id');
    }

    public function trainingSession()
    {
        return $this->belongsTo(TrainingSession::class);
    }

    public function sportRoute()
    {
        return $this->belongsTo(SportRoute::class, 'sport_route_id');
    }

    public function logs()
    {
        return $this->hasMany(TrainingLog::class, 'training_plan_item_id');
    }
}
