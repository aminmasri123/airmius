<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubGovernanceMeeting extends Model
{
    use HasFactory;

    public const TYPES = ['board', 'committee', 'general_assembly'];

    public const STATUSES = ['draft', 'invited', 'held', 'cancelled'];

    public const PARTICIPANT_SCOPES = ['body', 'club_members', 'custom'];

    protected $fillable = [
        'club_id', 'club_governance_body_id', 'club_year_period_id', 'type', 'title',
        'status', 'scheduled_at', 'location_name', 'participant_scope', 'eligibility_as_of', 'motions_due_on',
        'invitation_sent_at', 'agenda_items', 'materials', 'decision_templates', 'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'eligibility_as_of' => 'date',
            'motions_due_on' => 'date',
            'invitation_sent_at' => 'datetime',
            'agenda_items' => 'array',
            'materials' => 'array',
            'decision_templates' => 'array',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function governanceBody()
    {
        return $this->belongsTo(ClubGovernanceBody::class, 'club_governance_body_id');
    }

    public function yearPeriod()
    {
        return $this->belongsTo(ClubYearPeriod::class, 'club_year_period_id');
    }

    public function recipients()
    {
        return $this->hasMany(ClubGovernanceMeetingRecipient::class);
    }

    public function versions()
    {
        return $this->hasMany(ClubGovernanceMeetingVersion::class)->orderByDesc('version');
    }

    public function decisions()
    {
        return $this->hasMany(ClubGovernanceMeetingDecision::class);
    }

    public function createVersion(?int $userId = null): ClubGovernanceMeetingVersion
    {
        $version = ((int) $this->versions()->max('version')) + 1;

        return $this->versions()->create([
            'club_id' => $this->club_id,
            'version' => $version,
            'status' => $this->status,
            'scheduled_at' => $this->scheduled_at,
            'location_name' => $this->location_name,
            'eligibility_as_of' => $this->eligibility_as_of,
            'motions_due_on' => $this->motions_due_on,
            'invitation_sent_at' => $this->invitation_sent_at,
            'agenda_items' => $this->agenda_items ?? [],
            'materials' => $this->materials ?? [],
            'decision_templates' => $this->decision_templates ?? [],
            'notes' => $this->notes,
            'recipient_snapshot' => $this->recipients()
                ->get(['user_id', 'club_external_member_id', 'attendance_eligible', 'voting_eligible', 'eligibility_source', 'eligibility_role'])
                ->map(fn (ClubGovernanceMeetingRecipient $recipient) => [
                    'user_id' => $recipient->user_id,
                    'club_external_member_id' => $recipient->club_external_member_id,
                    'attendance_eligible' => (bool) $recipient->attendance_eligible,
                    'voting_eligible' => (bool) $recipient->voting_eligible,
                    'eligibility_source' => $recipient->eligibility_source,
                    'eligibility_role' => $recipient->eligibility_role,
                ])
                ->values()
                ->all(),
            'created_by' => $userId,
        ]);
    }
}
