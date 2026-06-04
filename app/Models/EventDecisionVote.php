<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventDecisionVote extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_decision_id',
        'event_decision_option_id',
        'user_id',
    ];

    public function decision()
    {
        return $this->belongsTo(EventDecision::class, 'event_decision_id');
    }

    public function option()
    {
        return $this->belongsTo(EventDecisionOption::class, 'event_decision_option_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

