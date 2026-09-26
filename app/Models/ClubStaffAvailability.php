<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubStaffAvailability extends Model
{
    use HasFactory;

    public const STATUSES = ['available', 'unavailable', 'tentative'];

    protected $fillable = [
        'club_id',
        'user_id',
        'created_by',
        'starts_at',
        'ends_at',
        'status',
        'source',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
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

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
