<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ride extends Model
{
    use HasFactory;

    public const VISIBILITIES = ['public', 'friends', 'club', 'team'];

    protected $fillable = [
        'club_id',
        'team_id',
        'driver_id',
        'visibility',
        'from',
        'to',
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
        return $this->belongsToMany(User::class, 'ride_users');
    }
}
