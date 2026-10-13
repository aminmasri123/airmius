<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubGovernancePosition extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id', 'club_governance_body_id', 'title', 'responsibilities', 'sort_order',
        'is_required', 'allows_multiple', 'is_public', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_required' => 'boolean',
            'allows_multiple' => 'boolean',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function body()
    {
        return $this->belongsTo(ClubGovernanceBody::class, 'club_governance_body_id');
    }

    public function assignments()
    {
        return $this->hasMany(ClubGovernanceAssignment::class, 'club_governance_position_id');
    }
}
