<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SponsorMembership extends Model
{
    use HasFactory;

    public const ROLE_OWNER = 'owner';

    public const ROLE_ADMINISTRATOR = 'administrator';

    public const ROLE_CAMPAIGN_MANAGER = 'campaign_manager';

    public const ROLE_FINANCE = 'finance';

    public const ROLE_VIEWER = 'viewer';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DECLINED = 'declined';

    public const ROLES = [
        self::ROLE_OWNER,
        self::ROLE_ADMINISTRATOR,
        self::ROLE_CAMPAIGN_MANAGER,
        self::ROLE_FINANCE,
        self::ROLE_VIEWER,
    ];

    protected $fillable = [
        'sponsor_id',
        'user_id',
        'email',
        'role',
        'status',
        'invited_by',
        'accepted_at',
        'last_accessed_at',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'last_accessed_at' => 'datetime',
        ];
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
