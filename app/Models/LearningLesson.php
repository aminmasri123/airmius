<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearningLesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'learning_course_id',
        'learning_course_section_id',
        'title',
        'type',
        'summary',
        'content',
        'video_url',
        'attachments',
        'duration_minutes',
        'position',
        'is_preview',
        'unlock_after_days',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'duration_minutes' => 'integer',
            'position' => 'integer',
            'is_preview' => 'boolean',
            'unlock_after_days' => 'integer',
        ];
    }

    public function course()
    {
        return $this->belongsTo(LearningCourse::class, 'learning_course_id');
    }

    public function section()
    {
        return $this->belongsTo(LearningCourseSection::class, 'learning_course_section_id');
    }

    public function comments()
    {
        return $this->hasMany(LearningLessonComment::class);
    }

    public function notes()
    {
        return $this->hasMany(LearningLessonNote::class);
    }

    public function progress()
    {
        return $this->hasMany(LearningLessonProgress::class);
    }

    public function assignments()
    {
        return $this->hasMany(LearningAssignment::class);
    }
}
