<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationJobInterest extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_job_id',
        'user_id',
        'name',
        'email',
        'phone',
        'message',
        'shared_profile_fields',
        'status',
        'internal_note',
        'consent_at',
        'profile_consent_at',
        'allow_in_app_contact',
        'conversation_id',
        'status_changed_at',
        'status_changed_by',
        'retention_expires_at',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'consent_at' => 'datetime',
            'profile_consent_at' => 'datetime',
            'shared_profile_fields' => 'array',
            'allow_in_app_contact' => 'boolean',
            'status_changed_at' => 'datetime',
            'retention_expires_at' => 'datetime',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(OrganizationJob::class, 'organization_job_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statusChangedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_changed_by');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
