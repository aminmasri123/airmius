<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubPolicyDocument extends Model
{
    use HasFactory;

    public const TYPES = [
        'statutes',
        'regulation',
        'contribution_model',
        'contract_register',
        'employment_model',
        'compensation_rules',
    ];

    public const PROTECTED_TYPES = [
        'contract_register',
        'employment_model',
        'compensation_rules',
    ];

    public const WORKFLOW_STATUSES = ['draft', 'in_review', 'approved', 'published', 'archived'];
    public const CLASSIFICATIONS = ['public', 'internal', 'confidential', 'restricted'];

    protected $fillable = [
        'club_id',
        'file_id',
        'created_by',
        'type',
        'title',
        'version_label',
        'valid_from',
        'valid_until',
        'contract_starts_on',
        'contract_ends_on',
        'cancellation_notice_days',
        'review_at',
        'review_job_id',
        'workflow_status',
        'classification',
        'retention_until',
        'approved_by',
        'approved_at',
        'published_by',
        'published_at',
        'publication_checksum',
        'archived_at',
        'is_public',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_until' => 'date',
            'contract_starts_on' => 'date',
            'contract_ends_on' => 'date',
            'cancellation_notice_days' => 'integer',
            'review_at' => 'date',
            'retention_until' => 'date',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
            'is_public' => 'boolean',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function file()
    {
        return $this->belongsTo(File::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function publishedBy()
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function contributionRules()
    {
        return $this->hasMany(ClubContributionRule::class, 'club_policy_document_id');
    }

    public function reviewJob()
    {
        return $this->belongsTo(WorkAutomationJob::class, 'review_job_id');
    }
}
