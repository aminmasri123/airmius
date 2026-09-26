<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubStaffAssignment extends Model
{
    use HasFactory;

    public const STATUSES = ['open', 'planned', 'confirmed', 'waitlisted', 'swap_requested', 'declined', 'cancelled'];

    protected $fillable = [
        'club_id',
        'event_id',
        'team_id',
        'user_id',
        'substitute_user_id',
        'assigned_by',
        'starts_at',
        'ends_at',
        'role',
        'required_qualifications',
        'status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'required_qualifications' => 'array',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function substitute()
    {
        return $this->belongsTo(User::class, 'substitute_user_id');
    }
}
