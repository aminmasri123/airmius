<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompetitionClub;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompetitionRegistration extends Model
{
    use BelongsToCompetitionClub;
    use HasFactory;

    protected $fillable = [
        'club_id',
        'competition_id',
        'competition_class_id',
        'team_id',
        'submitted_by',
        'status',
        'submitted_at',
        'confirmed_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'payload' => 'array',
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

    public function competitionClass()
    {
        return $this->belongsTo(CompetitionClass::class);
    }

    public function rosterEntries()
    {
        return $this->hasMany(CompetitionRosterEntry::class);
    }
}
