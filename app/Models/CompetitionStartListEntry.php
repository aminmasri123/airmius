<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompetitionClub;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompetitionStartListEntry extends Model
{
    use BelongsToCompetitionClub;
    use HasFactory;

    protected $fillable = [
        'club_id', 'competition_id', 'competition_class_id', 'competition_roster_entry_id',
        'event_id', 'heat', 'lane', 'start_number', 'scheduled_start_at', 'status', 'metadata',
    ];

    protected function casts(): array
    {
        return ['scheduled_start_at' => 'datetime', 'metadata' => 'array'];
    }
}
