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
        'content_type',
        'workflow_status',
        'recipient_snapshot_count',
        'recipient_snapshot_hash',
        'recipient_snapshot_at',
        'published_at',
        'notified_at',
        'submitted_for_review_at',
        'reviewed_by',
        'reviewed_at',
        'withdrawn_by',
        'withdrawn_at',
    ];

    protected function casts(): array
    {
        return [
            'recipient_snapshot_at' => 'datetime',
            'published_at' => 'datetime',
            'notified_at' => 'datetime',
            'submitted_for_review_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reads()
    {
        return $this->hasMany(ClubAnnouncementRead::class);
    }
}
