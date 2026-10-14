<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubStaffAssignment extends Model
{
    use HasFactory;

    public const STATUSES = ['open', 'planned', 'confirmed', 'waitlisted', 'swap_requested', 'declined', 'cancelled'];

    protected $fillable = [
        'club_id',
        'shift_key',
        'event_id',
        'team_id',
        'user_id',
        'external_member_id',
        'substitute_user_id',
        'assigned_by',
        'visibility_scope',
        'audience_team_ids',
        'audience_user_ids',
        'notify_audience',
        'starts_at',
        'ends_at',
        'signup_deadline',
        'role',
        'location',
        'instructions',
        'requirements',
        'contact_name',
        'contact_details',
        'required_qualifications',
        'status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'signup_deadline' => 'datetime',
            'required_qualifications' => 'array',
            'audience_team_ids' => 'array',
            'audience_user_ids' => 'array',
            'notify_audience' => 'boolean',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function externalMember()
    {
        return $this->belongsTo(ClubExternalMember::class, 'external_member_id');
    }

    public function substitute()
    {
        return $this->belongsTo(User::class, 'substitute_user_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
