<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompetitionClub;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompetitionLineupEntry extends Model
{
    use BelongsToCompetitionClub;
    use HasFactory;

    protected $fillable = [
        'club_id', 'competition_id', 'competition_lineup_id', 'competition_roster_entry_id',
        'user_id', 'display_name', 'role', 'position', 'sort_order', 'metadata',
    ];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'metadata' => 'array'];
    }
}
