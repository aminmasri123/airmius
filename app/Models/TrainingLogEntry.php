<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingLogEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_log_id',
        'title',
        'sets',
        'reps',
        'weight_kg',
        'duration_seconds',
        'distance_meters',
        'intensity',
        'notes',
        'metrics',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'weight_kg' => 'decimal:2',
            'metrics' => 'array',
        ];
    }

    public function log()
    {
        return $this->belongsTo(TrainingLog::class, 'training_log_id');
    }
}
