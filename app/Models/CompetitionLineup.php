<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompetitionClub;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompetitionLineup extends Model
{
    use BelongsToCompetitionClub;
    use HasFactory;

    protected $fillable = [
        'club_id', 'competition_id', 'competition_registration_id', 'competition_class_id', 'event_id',
        'sport_type', 'name', 'status', 'locked_at', 'published_at', 'metadata',
    ];

    protected function casts(): array
    {
        return ['locked_at' => 'datetime', 'published_at' => 'datetime', 'metadata' => 'array'];
    }

    public function entries()
    {
        return $this->hasMany(CompetitionLineupEntry::class);
    }
}
