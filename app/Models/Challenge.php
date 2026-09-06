<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Challenge extends Model
{
    use HasFactory;

    public const VISIBILITIES = ['public', 'club', 'team', 'invite_only'];

    public const METRICS = ['steps', 'distance_meters', 'duration_minutes', 'sessions', 'repetitions', 'calories', 'custom'];

    public const FREQUENCIES = ['daily', 'weekly', 'once'];

    public const VERIFICATIONS = ['manual', 'automatic', 'either'];

    public const STATUSES = ['published', 'cancelled'];

    protected $fillable = [
        'creator_id', 'sport_id', 'club_id', 'team_id', 'visibility', 'title', 'description',
        'metric', 'target_value', 'unit', 'frequency', 'checkin_slots', 'verification', 'starts_on', 'ends_on', 'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'target_value' => 'float',
            'checkin_slots' => 'array',
        ];
    }

    public function checkinSlots(): array
    {
        if ($this->frequency !== 'daily') {
            return ['anytime'];
        }

        $slots = array_values(array_unique(array_filter(
            $this->checkin_slots ?? [],
            fn ($slot) => in_array($slot, ['anytime', 'morning', 'midday', 'evening'], true),
        )));

        return $slots ?: ['anytime'];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function sport()
    {
        return $this->belongsTo(Sport::class);
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function participants()
    {
        return $this->hasMany(ChallengeParticipant::class);
    }

    public function checkins()
    {
        return $this->hasMany(ChallengeCheckin::class);
    }

    public function comments()
    {
        return $this->hasMany(ChallengeComment::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $visible) use ($user) {
            $visible->where('visibility', 'public')
                ->orWhere('creator_id', $user->id)
                ->orWhereHas('participants', fn (Builder $participants) => $participants->where('user_id', $user->id))
                ->orWhere(function (Builder $clubs) use ($user) {
                    $clubs->where('visibility', 'club')
                        ->whereHas('club.users', fn (Builder $members) => $members->where('users.id', $user->id));
                })
                ->orWhere(function (Builder $teams) use ($user) {
                    $teams->where('visibility', 'team')
                        ->whereHas('team.users', fn (Builder $members) => $members->where('users.id', $user->id));
                });
        });
    }
}
