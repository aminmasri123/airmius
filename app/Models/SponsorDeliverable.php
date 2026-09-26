<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SponsorDeliverable extends Model
{
    use HasFactory;

    public const STATUSES = [
        'planned',
        'in_progress',
        'fulfilled',
        'waived',
        'cancelled',
    ];

    protected $fillable = [
        'sponsor_id',
        'club_id',
        'responsible_user_id',
        'title',
        'location',
        'starts_at',
        'ends_at',
        'due_at',
        'status',
        'fulfillment_evidence',
        'fulfilled_at',
        'fulfilled_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'due_at' => 'date',
            'fulfilled_at' => 'datetime',
        ];
    }

    public function sponsor()
    {
        return $this->belongsTo(Sponsor::class);
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function fulfiller()
    {
        return $this->belongsTo(User::class, 'fulfilled_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['fulfilled', 'waived', 'cancelled']);
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null
            && $this->due_at->isPast()
            && ! in_array($this->status, ['fulfilled', 'waived', 'cancelled'], true);
    }
}
