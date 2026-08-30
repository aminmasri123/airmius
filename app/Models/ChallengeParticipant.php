<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChallengeParticipant extends Model
{
    protected $fillable = ['challenge_id', 'user_id', 'invited_by', 'status', 'responded_at', 'joined_at'];

    protected function casts(): array
    {
        return ['responded_at' => 'datetime', 'joined_at' => 'datetime'];
    }

    public function challenge() { return $this->belongsTo(Challenge::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function inviter() { return $this->belongsTo(User::class, 'invited_by'); }
}
