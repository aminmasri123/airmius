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
        'joined_on',
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
            'joined_on' => 'date',
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
