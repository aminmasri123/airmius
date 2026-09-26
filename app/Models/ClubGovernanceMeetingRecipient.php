<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubGovernanceMeetingRecipient extends Model
{
    use HasFactory;

    public const DELIVERY_STATUSES = ['pending', 'sent', 'delivered', 'failed', 'bounced'];

    protected $fillable = [
        'club_id', 'club_governance_meeting_id', 'user_id', 'club_external_member_id',
        'attendance_eligible', 'voting_eligible', 'eligibility_source', 'eligibility_role', 'delivery_status', 'delivered_at',
        'responded_at', 'response_status',
    ];

    protected function casts(): array
    {
        return [
            'attendance_eligible' => 'boolean',
            'voting_eligible' => 'boolean',
            'delivered_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function meeting()
    {
        return $this->belongsTo(ClubGovernanceMeeting::class, 'club_governance_meeting_id');
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
