<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubMembershipRequest extends Model
{
    protected $fillable = [
        'club_id',
        'user_id',
        'club_membership_type_id',
        'type',
        'status',
        'message',
        'application_data',
        'accepted_documents',
        'preferred_payment_method',
        'requested_billing_interval',
        'applicant_confirmed_at',
        'requested_pause_from',
        'requested_pause_until',
        'preview_amount',
        'preview_interval',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'requested_pause_from' => 'date',
            'requested_pause_until' => 'date',
            'application_data' => 'array',
            'accepted_documents' => 'array',
            'preview_amount' => 'decimal:2',
            'applicant_confirmed_at' => 'datetime',
            'reviewed_at' => 'datetime',
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
}
