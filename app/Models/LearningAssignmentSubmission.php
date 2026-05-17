<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearningAssignmentSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'learning_assignment_id',
        'learning_enrollment_id',
        'user_id',
        'body',
        'attachment_url',
        'status',
        'score',
        'feedback',
        'graded_by',
        'submitted_at',
        'graded_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'submitted_at' => 'datetime',
            'graded_at' => 'datetime',
        ];
    }

    public function assignment()
    {
        return $this->belongsTo(LearningAssignment::class, 'learning_assignment_id');
    }

    public function enrollment()
    {
        return $this->belongsTo(LearningEnrollment::class, 'learning_enrollment_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function grader()
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}
