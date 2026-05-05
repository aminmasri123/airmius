<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'created_by',
        'title',
        'type',
        'location',
        'workload',
        'employment_type',
        'description',
        'contact_email',
        'application_url',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function interests(): HasMany
    {
        return $this->hasMany(OrganizationJobInterest::class);
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }
}
