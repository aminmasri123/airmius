<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventAttendanceCorrection extends Model
{
    protected $fillable = [
        'event_id',
        'user_id',
        'actor_id',
        'source',
        'before_state',
        'after_state',
        'changed_fields',
        'reason_code',
        'contains_private_note',
    ];

    protected $casts = [
        'before_state' => 'array',
        'after_state' => 'array',
        'changed_fields' => 'array',
        'contains_private_note' => 'boolean',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
