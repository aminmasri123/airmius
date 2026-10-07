<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubMembershipRequest extends Model
{
    protected $fillable = [
        'club_id',
        'user_id',
        'club_membership_type_id',
        'club_department_id',
        'type',
        'status',
        'message',
        'information_request_message',
        'information_requested_by',
        'information_requested_at',
        'applicant_response_message',
        'applicant_responded_at',
        'waitlisted_by',
        'waitlisted_at',
        'application_data',
        'accepted_documents',
        'consent_version',
        'consent_signature',
        'consent_ip',
        'consent_user_agent',
        'consent_at',
        'preferred_payment_method',
        'requested_billing_interval',
        'applicant_confirmed_at',
        'requested_pause_from',
        'requested_pause_until',
        'requested_termination_on',
        'effective_on',
        'applied_at',
        'termination_reason',
        'preview_amount',
        'preview_base_amount',
        'preview_discount_amount',
        'preview_rule_type',
        'preview_interval',
        'preview_snapshot',
        'is_exception',
        'exception_reason',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'public_status_token',
    ];

    protected function casts(): array
    {
        return [
            'requested_pause_from' => 'date',
            'requested_pause_until' => 'date',
            'requested_termination_on' => 'date',
            'effective_on' => 'date',
            'applied_at' => 'datetime',
            'application_data' => 'array',
            'accepted_documents' => 'array',
            'consent_at' => 'datetime',
            'preview_amount' => 'decimal:2',
            'preview_base_amount' => 'decimal:2',
            'preview_discount_amount' => 'decimal:2',
            'preview_snapshot' => 'array',
            'applicant_confirmed_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'information_requested_at' => 'datetime',
            'applicant_responded_at' => 'datetime',
            'waitlisted_at' => 'datetime',
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

    public function membershipType()
    {
        return $this->belongsTo(ClubMembershipType::class, 'club_membership_type_id');
    }

    public function department()
    {
        return $this->belongsTo(ClubDepartment::class, 'club_department_id');
    }
}
