<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SportRouteTrack extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'sport_route_id',
        'sport_id',
        'team_id',
        'title',
        'sport_type',
        'status',
        'source',
        'started_at',
        'ended_at',
        'distance_meters',
        'duration_seconds',
        'elevation_gain_meters',
        'elevation_loss_meters',
        'average_speed_mps',
        'max_speed_mps',
        'track_points',
        'track_geometry',
        'metrics',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'average_speed_mps' => 'float',
            'max_speed_mps' => 'float',
            'track_points' => 'array',
            'track_geometry' => 'array',
            'metrics' => 'array',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function route()
    {
        return $this->belongsTo(SportRoute::class, 'sport_route_id');
    }

    public function sport()
    {
        return $this->belongsTo(Sport::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function trainingLogs()
    {
        return $this->hasMany(TrainingLog::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $teamIds = $user->teams()->pluck('teams.id')->all();

        return $query->where(function (Builder $visibilityQuery) use ($user, $teamIds) {
            $visibilityQuery
                ->where('user_id', $user->id)
                ->when($teamIds !== [], fn (Builder $teamQuery) => $teamQuery->orWhereIn('team_id', $teamIds));
        });
    }
}
