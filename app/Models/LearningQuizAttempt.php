<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearningQuizAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'learning_quiz_id',
        'learning_enrollment_id',
        'user_id',
        'answers',
        'correct_question_ids',
        'score_percent',
        'passed',
        'submitted_at',
        'question_snapshot', 'manual_grades', 'review_status', 'feedback', 'quiz_revision', 'pass_percent_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'question_snapshot' => 'array',
            'manual_grades' => 'array',
            'correct_question_ids' => 'array',
            'score_percent' => 'integer',
            'passed' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }

    public function quiz()
    {
        return $this->belongsTo(LearningQuiz::class, 'learning_quiz_id');
    }

    public function enrollment()
    {
        return $this->belongsTo(LearningEnrollment::class, 'learning_enrollment_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
