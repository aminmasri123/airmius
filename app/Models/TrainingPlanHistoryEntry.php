<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingPlanHistoryEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_plan_id',
        'training_plan_item_id',
        'actor_id',
        'event',
        'before',
        'after',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
        ];
    }

    public function plan()
    {
        return $this->belongsTo(TrainingPlan::class, 'training_plan_id');
    }

    public function item()
    {
        return $this->belongsTo(TrainingPlanItem::class, 'training_plan_item_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
