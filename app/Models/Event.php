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
    public const STATUSES = ['scheduled', 'cancelled'];

    protected $fillable = [
        'club_id',
        'user_id',
        'team_id',
        'conversation_id',
        'title',
        'type',
        'visibility',
        'status',
        'start_time',
        'end_time',
        'location',
        'location_name',
        'location_street',
        'location_house_number',
        'location_postal_code',
        'location_city',
        'location_country',
        'location_latitude',
        'location_longitude',
        'max_participants',
        'notes',
        'recurring',
        'recurrence_days',
        'recurrence_ends_at',
        'reminder_at',
        'reminder_sent_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'recurrence_days' => 'array',
        'recurrence_ends_at' => 'datetime',
        'reminder_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'location_latitude' => 'float',
        'location_longitude' => 'float',
        'max_participants' => 'integer',
    ];

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
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
