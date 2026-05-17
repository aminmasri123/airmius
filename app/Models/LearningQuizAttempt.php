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
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
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
