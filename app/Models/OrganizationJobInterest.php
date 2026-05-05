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
        'ip_address',
        'user_agent',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(OrganizationJob::class, 'organization_job_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
