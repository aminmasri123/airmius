<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompetitionClub;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompetitionPlayingTimeEntry extends Model
{
    use BelongsToCompetitionClub;
    use HasFactory;

    protected $fillable = [
        'club_id', 'competition_id', 'event_id', 'competition_roster_entry_id',
        'minutes_played', 'started_period', 'ended_period', 'segments',
    ];

    protected function casts(): array
    {
        return ['minutes_played' => 'integer', 'started_period' => 'integer', 'ended_period' => 'integer', 'segments' => 'array'];
    }
}
