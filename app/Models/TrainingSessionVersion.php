<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingSessionVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_session_id',
        'created_by',
        'revision',
        'change_note',
        'snapshot',
    ];

    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'snapshot' => 'array',
        ];
    }

    public function session()
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
