<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubGovernanceMeetingDecisionVote extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id', 'club_governance_meeting_decision_id', 'club_governance_meeting_recipient_id',
        'ballot_hash', 'user_id', 'club_external_member_id', 'choice', 'person_name', 'recipient_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'recipient_snapshot' => 'array',
        ];
    }

    public function decision()
    {
        return $this->belongsTo(ClubGovernanceMeetingDecision::class, 'club_governance_meeting_decision_id');
    }

    public function recipient()
    {
        return $this->belongsTo(ClubGovernanceMeetingRecipient::class, 'club_governance_meeting_recipient_id');
    }
}
