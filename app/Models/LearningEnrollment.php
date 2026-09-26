<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearningEnrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'learning_course_id',
        'user_id',
        'status',
        'progress_percent',
        'started_at',
        'completed_at',
        'public_status_token',
    ];

    protected function casts(): array
    {
        return [
            'progress_percent' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function course()
    {
        return $this->belongsTo(LearningCourse::class, 'learning_course_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lessonProgress()
    {
        return $this->hasMany(LearningLessonProgress::class, 'learning_enrollment_id');
    }

    public function quizAttempts()
    {
        return $this->hasMany(LearningQuizAttempt::class, 'learning_enrollment_id');
    }

    public function certificate()
    {
        return $this->hasOne(LearningCertificate::class, 'learning_enrollment_id');
    }

    public function assignmentSubmissions()
    {
        return $this->hasMany(LearningAssignmentSubmission::class, 'learning_enrollment_id');
    }
}
