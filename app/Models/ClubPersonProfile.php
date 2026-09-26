<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ClubPersonProfile extends Model
{
    public const PRIVACY_PUBLIC = 'public';

    public const PRIVACY_INTERNAL = 'internal';

    public const PRIVACY_CONFIDENTIAL = 'confidential';

    protected $guarded = ['id'];

    protected $casts = [
        'date_of_birth' => 'date:Y-m-d',
        'emergency_contact' => 'encrypted:array',
        'data_processing_flags' => 'array',
        'consent_recorded_at' => 'datetime',
    ];

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function membershipUser()
    {
        return $this->belongsTo(User::class, 'membership_user_id');
    }

    public function engagements()
    {
        return $this->hasMany(ClubEmploymentEngagement::class);
    }

    public function scopeForClub(Builder $query, Club|int $club): Builder
    {
        return $query->where('club_id', $club instanceof Club ? $club->id : $club);
    }

    public function hasSeparateMembershipLink(): bool
    {
        return $this->membership_user_id !== null && $this->membership_user_id !== $this->user_id;
    }
}
