<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventRecurrenceRuleVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'series_id', 'created_by', 'version', 'frequency', 'interval', 'days_of_week',
        'starts_at', 'ends_at', 'effective_from', 'effective_until', 'rule_payload',
    ];

    protected $casts = [
        'version' => 'integer',
        'interval' => 'integer',
        'days_of_week' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'effective_from' => 'datetime',
        'effective_until' => 'datetime',
        'rule_payload' => 'array',
    ];

    public function series()
    {
        return $this->belongsTo(EventRecurrenceSeries::class, 'series_id');
    }
}
