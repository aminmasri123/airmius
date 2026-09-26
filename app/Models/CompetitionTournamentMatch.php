<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompetitionClub;
use Illuminate\Database\Eloquent\Model;

class CompetitionTournamentMatch extends Model
{
    use BelongsToCompetitionClub;

    protected $fillable = [
        'club_id',
        'competition_id',
        'competition_class_id',
        'competition_tournament_group_id',
        'event_id',
        'home_roster_entry_id',
        'away_roster_entry_id',
        'phase',
        'round_number',
        'match_number',
        'home_label',
        'away_label',
        'home_score',
        'away_score',
        'status',
        'scheduled_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
