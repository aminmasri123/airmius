<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearningSecurityEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'learning_course_id',
        'learning_lesson_id',
        'user_id',
        'type',
        'severity',
        'ip_address',
        'user_agent',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
