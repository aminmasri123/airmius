<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class Competition extends Model
{
    use HasFactory;

    public const STATUSES = ['planned', 'open', 'closed', 'completed', 'cancelled'];

    protected $fillable = [
        'club_id',
        'team_id',
        'season_name',
        'season_key',
        'name',
        'code',
        'level',
        'status',
        'starts_on',
        'ends_on',
        'registration_deadline_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'registration_deadline_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $competition): void {
            if ($competition->team_id) {
                $teamClubId = Team::query()->whereKey($competition->team_id)->value('club_id');
                if ($teamClubId && (int) $teamClubId !== (int) $competition->club_id) {
                    throw ValidationException::withMessages([
                        'team_id' => __('validation.exists', ['attribute' => 'team_id']),
                    ]);
                }
            }

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

    public function classes()
    {
        return $this->hasMany(CompetitionClass::class);
    }

    public function opponents()
    {
        return $this->hasMany(CompetitionOpponent::class);
    }

    public function venues()
    {
        return $this->hasMany(CompetitionVenue::class);
    }

    public function registrations()
    {
        return $this->hasMany(CompetitionRegistration::class);
    }

    public function rosterEntries()
    {
        return $this->hasMany(CompetitionRosterEntry::class);
    }

    public function results()
    {
        return $this->hasMany(CompetitionResult::class);
    }

    public function tournamentGroups()
    {
        return $this->hasMany(CompetitionTournamentGroup::class);
    }

    public function tournamentMatches()
    {
        return $this->hasMany(CompetitionTournamentMatch::class);
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }
}
