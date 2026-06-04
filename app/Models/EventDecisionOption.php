<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventDecisionOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_decision_id',
        'label',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function decision()
    {
        return $this->belongsTo(EventDecision::class, 'event_decision_id');
    }

    public function votes()
    {
        return $this->hasMany(EventDecisionVote::class);
    }
}

