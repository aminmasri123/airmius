<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'user_id',
        'payment_id',
        'refund_payment_id',
        'replacement_for_participant_id',
        'status',
        'lifecycle_status',
        'rsvp_status',
        'attendance_status',
        'response_reason',
        'absence_reason',
        'camp_group_key',
        'camp_privacy_notice_accepted_at',
        'camp_travel_consent_accepted_at',
        'camp_guardian_consent_verified_at',
        'camp_emergency_contact_snapshot',
        'camp_dietary_notes',
        'response_mode',
        'responded_at',
        'waitlist_position',
        'waitlist_promoted_at',
        'waitlist_offer_expires_at',
        'cancelled_at',
        'refunded_at',
        'lifecycle_idempotency_key',
        'lifecycle_note',
        'checked_in_at',
        'check_in_method',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
        'waitlist_promoted_at' => 'datetime',
        'waitlist_offer_expires_at' => 'datetime',
        'camp_privacy_notice_accepted_at' => 'datetime',
        'camp_travel_consent_accepted_at' => 'datetime',
        'camp_guardian_consent_verified_at' => 'datetime',
        'camp_emergency_contact_snapshot' => 'array',
        'cancelled_at' => 'datetime',
        'refunded_at' => 'datetime',
        'checked_in_at' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function refundPayment()
    {
        return $this->belongsTo(Payment::class, 'refund_payment_id');
    }

    public function replacementFor()
    {
        return $this->belongsTo(self::class, 'replacement_for_participant_id');
    }
}
