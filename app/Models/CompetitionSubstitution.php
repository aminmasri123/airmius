<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompetitionClub;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompetitionSubstitution extends Model
{
    use BelongsToCompetitionClub;
    use HasFactory;

    protected $fillable = [
        'club_id', 'competition_id', 'event_id', 'out_roster_entry_id', 'in_roster_entry_id',
        'minute', 'period', 'reason', 'metadata',
    ];

    protected function casts(): array
    {
        return ['minute' => 'integer', 'metadata' => 'array'];
    }
}
