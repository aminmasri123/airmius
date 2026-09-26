<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubMembershipProspect extends Model
{
    use HasFactory;

    public const STATUSES = [
        'prospect',
        'trial_scheduled',
        'trial_completed',
        'application',
        'converted',
        'declined',
        'archived',
    ];

    public const TRIAL_OUTCOMES = ['interested', 'application', 'converted', 'no_show', 'declined'];

    protected $fillable = [
        'club_id',
        'user_id',
        'club_membership_request_id',
        'team_id',
        'club_membership_type_id',
        'name',
        'email',
        'phone',
        'status',
        'source',
        'trial_at',
        'trial_outcome',
        'notes',
        'created_by',
        'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'trial_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function membershipRequest()
    {
        return $this->belongsTo(ClubMembershipRequest::class, 'club_membership_request_id');
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function membershipType()
    {
        return $this->belongsTo(ClubMembershipType::class, 'club_membership_type_id');
    }
}
