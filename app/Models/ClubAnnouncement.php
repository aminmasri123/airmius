<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubAnnouncement extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'team_id',
        'user_id',
        'title',
        'body',
        'audience_type',
        'published_at',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function club() { return $this->belongsTo(Club::class); }
    public function team() { return $this->belongsTo(Team::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function reads() { return $this->hasMany(ClubAnnouncementRead::class); }
}
