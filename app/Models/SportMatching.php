<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SportMatching extends Model
{
    use HasFactory;

    public const MODES = ['partner', 'team'];
    public const SKILL_LEVELS = ['all', 'beginner', 'recreational', 'advanced', 'competitive'];
    public const STATUSES = ['open', 'matched', 'cancelled'];

    protected $fillable = [
        'user_id', 'sport_id', 'team_id', 'mode', 'title', 'description',
        'city', 'postal_code', 'location_name', 'address', 'country_code', 'latitude', 'longitude', 'radius_km',
        'starts_at', 'ends_at', 'participants_needed', 'team_size',
        'skill_level', 'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function sport() { return $this->belongsTo(Sport::class); }
    public function team() { return $this->belongsTo(Team::class); }
    public function applications() { return $this->hasMany(SportMatchingApplication::class); }
}
