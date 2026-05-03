<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubExternalMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'created_by',
        'linked_user_id',
        'name',
        'email',
        'role',
        'membership_status',
        'member_number',
        'athlete_license_number',
        'contribution_amount',
        'contribution_interval',
        'contribution_next_invoice_on',
        'contribution_last_invoice_at',
        'sepa_iban',
        'sepa_bic',
        'sepa_mandate_reference',
        'sepa_mandate_signed_on',
        'sepa_mandate_active',
        'joined_on',
        'membership_ends_on',
        'membership_end_notified_at',
        'membership_notes',
        'invitation_status',
        'invitation_token',
        'invited_at',
        'linked_at',
    ];

    protected function casts(): array
    {
        return [
            'contribution_amount' => 'decimal:2',
            'contribution_next_invoice_on' => 'date',
            'contribution_last_invoice_at' => 'datetime',
            'sepa_mandate_signed_on' => 'date',
            'sepa_mandate_active' => 'boolean',
            'joined_on' => 'date',
            'membership_ends_on' => 'date',
            'membership_end_notified_at' => 'datetime',
            'invited_at' => 'datetime',
            'linked_at' => 'datetime',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function linkedUser()
    {
        return $this->belongsTo(User::class, 'linked_user_id');
    }
}
