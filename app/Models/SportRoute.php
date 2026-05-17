<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SportRoute extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'sport_id',
        'team_id',
        'title',
        'description',
        'sport_type',
        'visibility',
        'status',
        'difficulty',
        'surface',
        'start_latitude',
        'start_longitude',
        'end_latitude',
        'end_longitude',
        'start_name',
        'end_name',
        'distance_meters',
        'estimated_duration_seconds',
        'elevation_gain_meters',
        'elevation_loss_meters',
        'waypoints',
        'route_geometry',
        'navigation_cues',
        'metrics',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'start_latitude' => 'float',
            'start_longitude' => 'float',
            'end_latitude' => 'float',
            'end_longitude' => 'float',
            'waypoints' => 'array',
            'route_geometry' => 'array',
            'navigation_cues' => 'array',
            'metrics' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sport()
    {
        return $this->belongsTo(Sport::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function tracks()
    {
        return $this->hasMany(SportRouteTrack::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $teamIds = $user->teams()->pluck('teams.id')->all();

        return $query->where(function (Builder $visibilityQuery) use ($user, $teamIds) {
            $visibilityQuery
                ->where('user_id', $user->id)
                ->orWhere('visibility', 'public')
                ->when($teamIds !== [], fn (Builder $teamQuery) => $teamQuery->orWhere(function (Builder $nested) use ($teamIds) {
                    $nested
                        ->where('visibility', 'team')
                        ->whereIn('team_id', $teamIds);
                }));
        });
    }
}
