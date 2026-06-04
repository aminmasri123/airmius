<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingBlockItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_block_id',
        'title',
        'notes',
        'sort_order',
        'meta',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'meta' => 'array',
    ];

    public function trainingBlock()
    {
        return $this->belongsTo(TrainingBlock::class);
    }
}

