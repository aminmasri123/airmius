<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'team_id',
        'title',
        'description',
        'cadence',
        'starts_on',
        'ends_on',
        'status',
        'share_permission',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'settings' => 'array',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function items()
    {
        return $this->hasMany(TrainingPlanItem::class)->orderBy('scheduled_at')->orderBy('sort_order');
    }

    public function assignments()
    {
        return $this->hasMany(TrainingPlanAssignment::class);
    }
}
