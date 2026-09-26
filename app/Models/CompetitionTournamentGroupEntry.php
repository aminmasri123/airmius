<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompetitionClub;
use Illuminate\Database\Eloquent\Model;

class CompetitionTournamentGroupEntry extends Model
{
    use BelongsToCompetitionClub;

    protected $fillable = [
        'club_id',
        'competition_id',
        'competition_tournament_group_id',
        'competition_roster_entry_id',
        'display_name',
        'seed',
        'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function rosterEntry()
    {
        return $this->belongsTo(CompetitionRosterEntry::class, 'competition_roster_entry_id');
    }
}
