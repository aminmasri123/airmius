<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubVolunteerProfile extends Model
{
    public const VISIBILITY_PRIVATE = 'private';

    public const VISIBILITY_CLUB_MANAGERS = 'club_managers';

    public const VISIBILITY_CLUB_MEMBERS = 'club_members';

    public const VISIBILITIES = [
        self::VISIBILITY_PRIVATE,
        self::VISIBILITY_CLUB_MANAGERS,
        self::VISIBILITY_CLUB_MEMBERS,
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'skills' => 'array',
            'interests' => 'array',
            'availability' => 'array',
            'workload_limit_minutes_per_week' => 'integer',
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
}
