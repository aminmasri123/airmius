<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompetitionClub;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompetitionRosterEntry extends Model
{
    use BelongsToCompetitionClub;
    use HasFactory;

    protected $fillable = [
        'club_id',
        'competition_id',
        'competition_registration_id',
        'competition_class_id',
        'user_id',
        'display_name',
        'bib_number',
        'role',
        'status',
        'metadata',
    ];

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

    public function registration()
    {
        return $this->belongsTo(CompetitionRegistration::class, 'competition_registration_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
