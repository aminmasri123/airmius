<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearningLessonProgress extends Model
{
    use HasFactory;

    protected $table = 'learning_lesson_progress';

    protected $fillable = [
        'learning_enrollment_id',
        'learning_lesson_id',
        'completed',
        'watch_seconds',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed' => 'boolean',
            'watch_seconds' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function enrollment()
    {
        return $this->belongsTo(LearningEnrollment::class, 'learning_enrollment_id');
    }

    public function lesson()
    {
        return $this->belongsTo(LearningLesson::class, 'learning_lesson_id');
    }
}
