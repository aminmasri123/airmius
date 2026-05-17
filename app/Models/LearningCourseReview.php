<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearningCourseReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'learning_course_id',
        'user_id',
        'rating',
        'body',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
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
}
