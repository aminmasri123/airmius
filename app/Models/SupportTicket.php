<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'club_id',
        'assigned_to',
        'name',
        'email',
        'subject',
        'message',
        'category',
        'priority',
        'status',
        'last_reply_at',
        'response_sla_target_minutes',
        'response_due_at',
        'first_response_at',
        'sla_target_minutes',
        'sla_policy_version',
        'due_at',
        'escalated_at',
        'resolved_at',
        'admin_note',
    ];

    protected function casts(): array
    {
        return [
            'last_reply_at' => 'datetime',
            'response_due_at' => 'datetime',
            'first_response_at' => 'datetime',
            'due_at' => 'datetime',
            'escalated_at' => 'datetime',
            'resolved_at' => 'datetime',
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

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
