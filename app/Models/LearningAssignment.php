<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearningAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'learning_course_id',
        'learning_lesson_id',
        'title',
        'instructions',
        'points',
        'due_after_days',
        'is_required',
        'questions', 'revision', 'grading_mode', 'pass_percent',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer', 'pass_percent' => 'integer',
            'questions' => 'array',
            'revision' => 'integer',
            'due_after_days' => 'integer',
            'is_required' => 'boolean',
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

    public function submissions()
    {
        return $this->hasMany(LearningAssignmentSubmission::class);
    }
}
