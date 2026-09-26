<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompetitionClub;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompetitionResult extends Model
{
    use BelongsToCompetitionClub;
    use HasFactory;

    protected $fillable = [
        'club_id',
        'competition_id',
        'competition_class_id',
        'competition_opponent_id',
        'competition_roster_entry_id',
        'event_id',
        'status',
        'rank',
        'score',
        'result_text',
        'details',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'rank' => 'integer',
            'score' => 'float',
            'details' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function competition()
    {
        return $this->belongsTo(Competition::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
