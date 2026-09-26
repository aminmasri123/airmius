<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubGovernanceAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id', 'club_governance_body_id', 'user_id', 'club_external_member_id',
        'position_title', 'responsibilities', 'starts_on', 'ends_on', 'is_public',
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

    public function body()
    {
        return $this->belongsTo(ClubGovernanceBody::class, 'club_governance_body_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function externalMember()
    {
        return $this->belongsTo(ClubExternalMember::class, 'club_external_member_id');
    }
}
