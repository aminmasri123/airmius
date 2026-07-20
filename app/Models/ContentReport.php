<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'reporter_id',
        'reportable_type',
        'reportable_id',
        'reason',
        'details',
        'status',
        'reviewed_by',
        'reviewed_at',
        'decision_reason',
        'action_taken',
        'appeal_reason',
        'appeal_status',
        'appealed_at',
        'appeal_decision',
        'appeal_decided_by',
        'appeal_decided_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'appealed_at' => 'datetime',
            'appeal_decided_at' => 'datetime',
        ];
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function reportable()
    {
        return $this->morphTo();
    }

    public function appealReviewer()
    {
        return $this->belongsTo(User::class, 'appeal_decided_by');
    }

    public function logs()
    {
        return $this->hasMany(ModerationLog::class, 'case_id')
            ->where('case_type', self::class);
    }
}
