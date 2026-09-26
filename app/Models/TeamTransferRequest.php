<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamTransferRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'user_id',
        'source_team_id',
        'target_team_id',
        'role',
        'status',
        'origin',
        'effective_on',
        'message',
        'requested_by',
        'decided_by',
        'decided_at',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_on' => 'date:Y-m-d',
            'decided_at' => 'datetime',
            'applied_at' => 'datetime',
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

    public function sourceTeam()
    {
        return $this->belongsTo(Team::class, 'source_team_id');
    }

    public function targetTeam()
    {
        return $this->belongsTo(Team::class, 'target_team_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
