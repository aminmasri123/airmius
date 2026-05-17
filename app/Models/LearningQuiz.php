<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearningQuiz extends Model
{
    use HasFactory;

    protected $fillable = [
        'learning_course_id',
        'learning_lesson_id',
        'title',
        'description',
        'pass_percent',
    ];

    protected function casts(): array
    {
        return [
            'pass_percent' => 'integer',
        ];
    }

    public function course()
    {
        return $this->belongsTo(LearningCourse::class, 'learning_course_id');
    }

    public function lesson()
    {
        return $this->belongsTo(LearningLesson::class, 'learning_lesson_id');
    }

    public function questions()
    {
        return $this->hasMany(LearningQuizQuestion::class)->orderBy('position')->orderBy('id');
    }

    public function attempts()
    {
        return $this->hasMany(LearningQuizAttempt::class);
    }
}
