<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubGovernanceMeetingDecision extends Model
{
    use HasFactory;

    public const TYPES = ['motion', 'election'];

    public const VOTING_MODES = ['open', 'named', 'secret'];

    public const STATUSES = ['open', 'closed'];

    public const MAJORITY_RULES = ['simple', 'absolute'];

    protected $fillable = [
        'club_id', 'club_governance_meeting_id', 'club_policy_document_id', 'contract_review_status',
        'external_review', 'ballot_salt_hash', 'meeting_version', 'type', 'voting_mode',
        'title', 'description', 'status', 'majority_rule', 'quorum', 'options', 'eligible_voters',
        'recipient_snapshot', 'result_snapshot', 'outcome', 'correction_locked_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'external_review' => 'array',
            'recipient_snapshot' => 'array',
            'result_snapshot' => 'array',
            'correction_locked_at' => 'datetime',
        ];
    }

    public function meeting()
    {
        return $this->belongsTo(ClubGovernanceMeeting::class, 'club_governance_meeting_id');
    }

    public function votes()
    {
        return $this->hasMany(ClubGovernanceMeetingDecisionVote::class);
    }

    public function policyDocument()
    {
        return $this->belongsTo(ClubPolicyDocument::class, 'club_policy_document_id');
    }
}
