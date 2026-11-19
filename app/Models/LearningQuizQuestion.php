<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearningQuizQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'learning_quiz_id',
        'question',
        'options',
        'correct_options',
        'explanation',
        'position',
        'question_type',
        'image_url',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'correct_options' => 'array',
            'position' => 'integer',
        ];
    }

    public function quiz()
    {
        return $this->belongsTo(LearningQuiz::class, 'learning_quiz_id');
    }
}
