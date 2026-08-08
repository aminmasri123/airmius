<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebsiteRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'club_id',
        'guest_name',
        'guest_email',
        'guest_phone',
        'club_name',
        'status',
        'package',
        'domain',
        'goals',
        'notes',
        'consent_at',
        'status_changed_at',
        'status_changed_by',
        'retention_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'consent_at' => 'datetime',
            'status_changed_at' => 'datetime',
            'retention_expires_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function statusChangedBy()
    {
        return $this->belongsTo(User::class, 'status_changed_by');
    }
}
