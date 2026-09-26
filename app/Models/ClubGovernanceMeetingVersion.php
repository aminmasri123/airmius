<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubGovernanceMeetingVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id', 'club_governance_meeting_id', 'version', 'status', 'scheduled_at',
        'location_name', 'eligibility_as_of', 'motions_due_on', 'invitation_sent_at', 'agenda_items',
        'materials', 'decision_templates', 'notes', 'recipient_snapshot', 'created_by',
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
            'recipient_snapshot' => 'array',
        ];
    }

    public function meeting()
    {
        return $this->belongsTo(ClubGovernanceMeeting::class, 'club_governance_meeting_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
