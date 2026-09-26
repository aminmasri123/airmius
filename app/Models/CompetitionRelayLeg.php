<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompetitionClub;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompetitionRelayLeg extends Model
{
    use BelongsToCompetitionClub;
    use HasFactory;

    protected $fillable = [
        'club_id', 'competition_id', 'competition_relay_team_id', 'competition_roster_entry_id',
        'user_id', 'leg_number', 'segment', 'metadata',
    ];

    protected function casts(): array
    {
        return ['leg_number' => 'integer', 'metadata' => 'array'];
    }
}
