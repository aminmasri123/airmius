<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SportPlace extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'sport_id',
        'team_id',
        'name',
        'type',
        'description',
        'latitude',
        'longitude',
        'address',
        'city',
        'country_code',
        'visibility',
        'status',
        'sport_types',
        'amenities',
        'surfaces',
        'opening_hours',
        'rating_avg',
        'rating_count',
        'verified_at',
        'metrics',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'sport_types' => 'array',
            'amenities' => 'array',
            'surfaces' => 'array',
            'rating_avg' => 'float',
            'verified_at' => 'datetime',
            'metrics' => 'array',
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
