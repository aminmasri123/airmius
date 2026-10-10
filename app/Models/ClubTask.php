<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubTask extends Model
{
    protected $fillable = [
        'club_id',
        'team_id',
        'created_by',
        'assigned_to',
        'title',
        'description',
        'status',
        'priority',
        'visibility',
        'start_at',
        'due_at',
        'participant_ids',
        'checklist',
        'attachment_links',
        'completed_at',
    ];

    protected $casts = [
        'start_at' => 'date:Y-m-d',
        'due_at' => 'date:Y-m-d',
        'participant_ids' => 'array',
        'checklist' => 'array',
        'attachment_links' => 'array',
        'completed_at' => 'datetime',
    ];

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function comments()
    {
        return $this->hasMany(ClubTaskComment::class);
    }

    public function attachments()
    {
        return $this->belongsToMany(File::class, 'club_task_attachments')
            ->withPivot('uploaded_by')
            ->withTimestamps();
    }
}
