<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModerationFlag extends Model
{
    use HasFactory;

    protected $fillable = [
        'flaggable_type',
        'flaggable_id',
        'user_id',
        'source',
        'severity',
        'categories',
        'matched_terms',
        'status',
        'automated_action',
        'reviewed_by',
        'reviewed_at',
        'decision_reason',
        'action_taken',
    ];

    protected function casts(): array
    {
        return [
            'categories' => 'array',
            'matched_terms' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function flaggable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function logs()
    {
        return $this->hasMany(ModerationLog::class, 'case_id')
            ->where('case_type', self::class);
    }
}
