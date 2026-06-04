<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventDecision extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'user_id',
        'question',
        'description',
        'closes_at',
        'status',
    ];

    protected $casts = [
        'closes_at' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function options()
    {
        return $this->hasMany(EventDecisionOption::class)->orderBy('sort_order');
    }

    public function votes()
    {
        return $this->hasMany(EventDecisionVote::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open' && (! $this->closes_at || now()->lt($this->closes_at));
    }
}

