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
        'assignment_status',
        'assignment_mode',
        'title',
        'description',
        'status',
        'priority',
        'visibility',
        'start_at',
        'due_at',
        'participant_ids',
        'participant_progress',
        'checklist',
        'attachment_links',
        'activity_log',
        'completed_at',
    ];

    protected $casts = [
        'start_at' => 'date:Y-m-d',
        'due_at' => 'date:Y-m-d',
        'assignment_status' => 'array',
        'participant_ids' => 'array',
        'participant_progress' => 'array',
        'checklist' => 'array',
        'attachment_links' => 'array',
        'activity_log' => 'array',
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
