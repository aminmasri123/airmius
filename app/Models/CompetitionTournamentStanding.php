<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompetitionClub;
use Illuminate\Database\Eloquent\Model;

class CompetitionTournamentStanding extends Model
{
    use BelongsToCompetitionClub;

    protected $fillable = [
        'club_id',
        'competition_id',
        'competition_tournament_group_id',
        'competition_roster_entry_id',
        'display_name',
        'rank',
        'played',
        'wins',
        'draws',
        'losses',
        'points',
        'score_for',
        'score_against',
        'score_difference',
        'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
