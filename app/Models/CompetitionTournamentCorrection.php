<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompetitionClub;
use Illuminate\Database\Eloquent\Model;

class CompetitionTournamentCorrection extends Model
{
    use BelongsToCompetitionClub;

    protected $fillable = [
        'club_id',
        'competition_id',
        'competition_tournament_match_id',
        'actor_id',
        'correction_type',
        'before',
        'after',
        'conflicts',
        'status',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'conflicts' => 'array',
        ];
    }
}
