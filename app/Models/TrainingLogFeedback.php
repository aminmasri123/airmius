<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingLogFeedback extends Model
{
    use HasFactory;

    protected $table = 'training_log_feedback';

    protected $fillable = [
        'training_log_id',
        'user_id',
        'body',
        'role',
    ];

    public function log()
    {
        return $this->belongsTo(TrainingLog::class, 'training_log_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
