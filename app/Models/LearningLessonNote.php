<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearningLessonNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'learning_lesson_id',
        'user_id',
        'body',
    ];

    public function lesson()
    {
        return $this->belongsTo(LearningLesson::class, 'learning_lesson_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
