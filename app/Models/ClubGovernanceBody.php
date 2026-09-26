<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubGovernanceBody extends Model
{
    use HasFactory;

    public const TYPES = ['board', 'committee', 'working_group'];

    protected $fillable = [
        'club_id', 'type', 'name', 'description', 'starts_on', 'ends_on', 'is_public',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_public' => 'boolean',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function assignments()
    {
        return $this->hasMany(ClubGovernanceAssignment::class);
    }
}
