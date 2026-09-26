<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompetitionClub;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompetitionOpponent extends Model
{
    use BelongsToCompetitionClub;
    use HasFactory;

    protected $fillable = ['club_id', 'competition_id', 'name', 'club_name', 'external_identifier', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function competition()
    {
        return $this->belongsTo(Competition::class);
    }
}
