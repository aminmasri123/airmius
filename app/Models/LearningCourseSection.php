<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearningCourseSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'learning_course_id',
        'title',
        'description',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function course()
    {
        return $this->belongsTo(LearningCourse::class, 'learning_course_id');
    }

    public function lessons()
    {
        return $this->hasMany(LearningLesson::class)->orderBy('position')->orderBy('id');
    }
}
