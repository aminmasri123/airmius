<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompetitionClub;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompetitionClass extends Model
{
    use BelongsToCompetitionClub;
    use HasFactory;

    protected $fillable = ['club_id', 'competition_id', 'name', 'age_group', 'gender', 'discipline', 'rules'];

    protected function casts(): array
    {
        return ['rules' => 'array'];
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
