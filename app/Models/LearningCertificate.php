<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearningCertificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'learning_course_id',
        'learning_enrollment_id',
        'user_id',
        'code',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
        ];
    }

    public function course()
    {
        return $this->belongsTo(LearningCourse::class, 'learning_course_id');
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
