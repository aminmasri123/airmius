<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SportMatchingAttendance extends Model
{
    use HasFactory;

    public const STATUSES = ['pending', 'confirmed', 'cancelled', 'checked_in', 'no_show'];

    protected $fillable = [
        'sport_matching_id',
        'sport_matching_application_id',
        'user_id',
        'status',
        'confirmed_at',
        'checked_in_at',
        'no_show_reported_at',
        'no_show_reported_by',
        'no_show_reason',
        'reminder_24h_sent_at',
        'reminder_2h_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'no_show_reported_at' => 'datetime',
            'reminder_24h_sent_at' => 'datetime',
            'reminder_2h_sent_at' => 'datetime',
        ];
    }

    public function matching() { return $this->belongsTo(SportMatching::class, 'sport_matching_id'); }
    public function application() { return $this->belongsTo(SportMatchingApplication::class, 'sport_matching_application_id'); }
    public function user() { return $this->belongsTo(User::class); }
    public function noShowReporter() { return $this->belongsTo(User::class, 'no_show_reported_by'); }
}
