<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GuardianChildRelationship extends Model
{
    use HasFactory;

    public const STATUS_INVITED = 'invited';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_AMBIGUOUS = 'ambiguous';

    protected $fillable = [
        'club_id',
        'child_user_id',
        'guardian_user_id',
        'guardian_email',
        'relationship_type',
        'is_primary',
        'status',
        'valid_from',
        'valid_until',
        'invited_at',
        'accepted_at',
        'declined_at',
        'revoked_at',
        'created_by_user_id',
        'updated_by_user_id',
        'backfilled_from_legacy',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'valid_from' => 'date:Y-m-d',
            'valid_until' => 'date:Y-m-d',
            'invited_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
            'revoked_at' => 'datetime',
            'backfilled_from_legacy' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function child()
    {
        return $this->belongsTo(User::class, 'child_user_id');
    }

    public function guardian()
    {
        return $this->belongsTo(User::class, 'guardian_user_id');
    }
}
