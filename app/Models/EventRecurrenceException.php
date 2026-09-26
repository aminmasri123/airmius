<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventRecurrenceException extends Model
{
    use HasFactory;

    public const KINDS = ['cancelled', 'rescheduled', 'holiday', 'school_holiday', 'blackout', 'seasonal_adjustment'];

    protected $fillable = [
        'series_id', 'rule_version_id', 'kind', 'local_date', 'starts_at', 'ends_at',
        'timezone', 'name', 'payload',
    ];

    protected $casts = [
        'local_date' => 'date',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'payload' => 'array',
    ];
}
