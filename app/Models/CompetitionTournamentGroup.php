<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompetitionClub;
use Illuminate\Database\Eloquent\Model;

class CompetitionTournamentGroup extends Model
{
    use BelongsToCompetitionClub;

    protected $fillable = ['club_id', 'competition_id', 'competition_class_id', 'name', 'sort_order', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function entries()
    {
        return $this->hasMany(CompetitionTournamentGroupEntry::class);
    }

    public function matches()
    {
        return $this->hasMany(CompetitionTournamentMatch::class);
    }
}
