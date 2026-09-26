<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamMemberAssignment extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_INJURED = 'injured';
    public const STATUS_LEFT = 'left';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_PAUSED,
        self::STATUS_INJURED,
        self::STATUS_LEFT,
    ];

    protected $fillable = [
        'club_id', 'team_id', 'user_id', 'sport_year_period_id', 'club_training_group_id',
        'role', 'position', 'jersey_number', 'status', 'is_guest_participation',
        'valid_from', 'valid_until', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_guest_participation' => 'boolean',
            'valid_from' => 'date:Y-m-d',
            'valid_until' => 'date:Y-m-d',
            'metadata' => 'array',
        ];
    }

    public function scopeOverlapping(Builder $query, string $validFrom, ?string $validUntil): Builder
    {
        return $query
            ->whereDate('valid_from', '<=', $validUntil ?: '9999-12-31')
            ->where(function (Builder $query) use ($validFrom): void {
                $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $validFrom);
            });
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sportYearPeriod()
    {
        return $this->belongsTo(ClubYearPeriod::class, 'sport_year_period_id');
    }

    public function trainingGroup()
    {
        return $this->belongsTo(ClubTrainingGroup::class, 'club_training_group_id');
    }
}
