<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventRecurrenceSeries extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id', 'team_id', 'created_by', 'title', 'timezone', 'starts_at', 'ends_at',
        'active_from', 'active_until', 'current_version',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'active_from' => 'datetime',
        'active_until' => 'datetime',
        'current_version' => 'integer',
    ];

    public function rules()
    {
        return $this->hasMany(EventRecurrenceRuleVersion::class, 'series_id');
    }

    public function exceptions()
    {
        return $this->hasMany(EventRecurrenceException::class, 'series_id');
    }
}
