<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingPlanHandover extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_plan_id',
        'from_user_id',
        'to_user_id',
        'created_by',
        'starts_at',
        'ends_at',
        'status',
        'handover_note',
        'responsibilities',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'responsibilities' => 'array',
        ];
    }

    public function plan()
    {
        return $this->belongsTo(TrainingPlan::class, 'training_plan_id');
    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
