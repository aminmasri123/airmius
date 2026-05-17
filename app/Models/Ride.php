<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ride extends Model
{
    use HasFactory;

    public const VISIBILITIES = ['public', 'friends', 'club', 'team'];
    public const MEMBER_STATUS_ACCEPTED = 'accepted';
    public const MEMBER_STATUS_REQUESTED = 'requested';
    public const MEMBER_STATUS_REJECTED = 'rejected';
    public const MEMBER_STATUS_PENDING = self::MEMBER_STATUS_REQUESTED;
    public const MEMBER_STATUS_CONFIRMED = self::MEMBER_STATUS_ACCEPTED;

    protected $fillable = [
        'club_id',
        'team_id',
        'driver_id',
        'visibility',
        'from',
        'to',
        'pickup_name',
        'pickup_street',
        'pickup_house_number',
        'pickup_postal_code',
        'pickup_city',
        'pickup_country',
        'pickup_note',
        'departure_time',
        'seats',
        'contact_details',
    ];

    protected function casts(): array
    {
        return [
            'departure_time' => 'datetime',
        ];
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'ride_users')
            ->withPivot(['status', 'message', 'responded_at'])
            ->withTimestamps();
    }

    public function acceptedUsers()
    {
        return $this->users()->wherePivot('status', self::MEMBER_STATUS_ACCEPTED);
    }

    public function pendingUsers()
    {
        return $this->users()->wherePivot('status', self::MEMBER_STATUS_REQUESTED);
    }
}
