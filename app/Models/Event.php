<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    public const TYPES = ['training', 'match', 'meeting', 'public'];
    public const VISIBILITIES = ['private', 'organization', 'public'];
    public const PARTICIPANT_STATUSES = ['yes', 'no', 'maybe'];

    protected $fillable = [
        'club_id',
        'team_id',
        'conversation_id',
        'title',
        'type',
        'visibility',
        'start_time',
        'end_time',
        'location',
        'notes',
        'recurring',
        'recurrence_days',
        'recurrence_ends_at',
        'reminder_at',
    ];

  protected $casts = [
    'start_time' => 'datetime',
    'end_time' => 'datetime',
    'recurrence_days' => 'array',
    'recurrence_ends_at' => 'datetime',
    'reminder_at' => 'datetime',
];

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function participants()
    {
        return $this->belongsToMany(User::class, 'event_participants')
            ->withPivot('status')
            ->withTimestamps();
    }

    public function participantRecords()
    {
        return $this->hasMany(EventParticipant::class);
    }

    public function comments()
    {
        return $this->hasMany(EventComment::class);
    }

    public function files()
    {
        return $this->hasMany(File::class);
    }

    public function resolvedClub(): ?Club
    {
        return $this->club ?: $this->team?->club;
    }
}
