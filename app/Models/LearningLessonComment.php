<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearningLessonComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'learning_lesson_id',
        'user_id',
        'parent_id',
        'body',
        'visibility',
    ];

    public function lesson()
    {
        return $this->belongsTo(LearningLesson::class, 'learning_lesson_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function replies()
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
