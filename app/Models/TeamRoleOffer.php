<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamRoleOffer extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'responded_at' => 'datetime', 'membership_created_at' => 'datetime'];
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function proposer()
    {
        return $this->belongsTo(User::class, 'proposer_id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}
