<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'club_id',
        'club_department_id',
        'team_id',
        'assigned_to',
        'name',
        'email',
        'subject',
        'message',
        'category',
        'priority',
        'status',
        'is_confidential',
        'is_anonymous',
        'allow_follow_up',
        'safety_report_type',
        'confidential_case_group',
        'responsible_user_id',
        'conflict_user_ids',
        'protective_action_summary',
        'affected_person_reference',
        'report_source',
        'confidential_at',
        'last_reply_at',
        'response_sla_target_minutes',
        'response_due_at',
        'first_response_at',
        'sla_target_minutes',
        'sla_policy_version',
        'due_at',
        'escalated_at',
        'resolved_at',
        'admin_note',
    ];

    protected function casts(): array
    {
        return [
            'last_reply_at' => 'datetime',
            'response_due_at' => 'datetime',
            'first_response_at' => 'datetime',
            'due_at' => 'datetime',
            'escalated_at' => 'datetime',
            'resolved_at' => 'datetime',
            'is_confidential' => 'boolean',
            'is_anonymous' => 'boolean',
            'allow_follow_up' => 'boolean',
            'confidential_at' => 'datetime',
            'conflict_user_ids' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function department()
    {
        return $this->belongsTo(ClubDepartment::class, 'club_department_id');
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function responsibleUser()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function confidentialAudits()
    {
        return $this->hasMany(SupportTicketConfidentialAudit::class);
    }
}
