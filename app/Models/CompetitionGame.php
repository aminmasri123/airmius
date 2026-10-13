<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompetitionClub;
use Illuminate\Database\Eloquent\Model;

class CompetitionGame extends Model
{
    use BelongsToCompetitionClub;

    protected $fillable = ['club_id', 'competition_id', 'team_id', 'opponent', 'scheduled_at', 'sport_type'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime'];
    }

    public function lineup()
    {
        return $this->hasOne(CompetitionLineup::class);
    }
}
