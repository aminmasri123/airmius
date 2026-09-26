<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventCheckInToken extends Model
{
    protected $fillable = [
        'event_id',
        'user_id',
        'issued_by',
        'token_hash',
        'device_hash',
        'valid_from',
        'expires_at',
        'used_at',
        'revoked_at',
        'attempt_count',
    ];

    protected $casts = [
        'valid_from' => 'datetime',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'revoked_at' => 'datetime',
        'attempt_count' => 'integer',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
