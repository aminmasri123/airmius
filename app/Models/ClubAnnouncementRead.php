<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubAnnouncementRead extends Model
{
    use HasFactory;

    protected $fillable = ['club_announcement_id', 'user_id', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function announcement() { return $this->belongsTo(ClubAnnouncement::class, 'club_announcement_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
