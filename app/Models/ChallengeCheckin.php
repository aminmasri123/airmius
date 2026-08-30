<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChallengeCheckin extends Model
{
    protected $fillable = ['challenge_id', 'user_id', 'checkin_date', 'value', 'completed', 'source', 'note'];

    protected function casts(): array
    {
        return ['checkin_date' => 'date', 'value' => 'float', 'completed' => 'boolean'];
    }

    public function challenge() { return $this->belongsTo(Challenge::class); }
    public function user() { return $this->belongsTo(User::class); }
}
