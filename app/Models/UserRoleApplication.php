<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class UserRoleApplication extends Model
{
    use HasFactory;

    public const TYPE_TRAINER = 'trainer';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'type',
        'status',
        'message',
        'application_data',
        'role_activated',
        'review_notes',
        'requested_at',
        'reviewed_at',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'role_activated' => 'boolean',
            'application_data' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
