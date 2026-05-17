<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearningEmailDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'learning_course_id',
        'learning_enrollment_id',
        'user_id',
        'learning_lesson_id',
        'type',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }
}
