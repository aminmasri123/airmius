<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'created_by',
        'trainer_id',
        'team_id',
        'training_plan_id',
        'training_plan_item_id',
        'sport_route_id',
        'sport_route_track_id',
        'sport_type',
        'title',
        'status',
        'performed_at',
        'duration_minutes',
        'distance_meters',
        'calories',
        'intensity',
        'notes',
        'trainer_feedback',
        'metrics',
    ];

    protected function casts(): array
    {
        return [
            'performed_at' => 'datetime',
            'metrics' => 'array',
        ];
    }

    public function athlete()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function trainer()
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function plan()
    {
        return $this->belongsTo(TrainingPlan::class, 'training_plan_id');
    }

    public function planItem()
    {
        return $this->belongsTo(TrainingPlanItem::class, 'training_plan_item_id');
    }

    public function sportRoute()
    {
        return $this->belongsTo(SportRoute::class, 'sport_route_id');
    }

    public function sportRouteTrack()
    {
        return $this->belongsTo(SportRouteTrack::class, 'sport_route_track_id');
    }

    public function entries()
    {
        return $this->hasMany(TrainingLogEntry::class)->orderBy('sort_order')->orderBy('id');
    }

    public function feedbacks()
    {
        return $this->hasMany(TrainingLogFeedback::class)->with('author:id,name,first_name,last_name,email')->oldest();
    }
}
