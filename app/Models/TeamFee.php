<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamFee extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'user_id',
        'collector_id',
        'category',
        'amount',
        'currency',
        'status',
        'note',
        'due_date',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'due_date' => 'date',
        'paid_at' => 'date',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function member()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function collector()
    {
        return $this->belongsTo(User::class, 'collector_id');
    }
}

