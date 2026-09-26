<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkAutomationJob extends Model
{
    use HasFactory;

    public const KIND_REMINDER = 'reminder';
    public const KIND_ESCALATION = 'escalation';

    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'club_id',
        'created_by',
        'retried_by',
        'kind',
        'status',
        'idempotency_key',
        'subject_type',
        'subject_id',
        'recipient_roles',
        'payload',
        'attempts',
        'queued_at',
        'started_at',
        'completed_at',
        'failed_at',
        'retry_queued_at',
        'error_code',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'recipient_roles' => 'array',
            'payload' => 'array',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'retry_queued_at' => 'datetime',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }
}
