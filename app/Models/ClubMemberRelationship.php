<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubMemberRelationship extends Model
{
    use HasFactory;

    public const PURPOSE_CONTRIBUTION_PAYER = 'contribution_payer';
    public const PURPOSE_GUARDIAN = 'guardian';
    public const PURPOSE_EMERGENCY_CONTACT = 'emergency_contact';
    public const PURPOSE_PICKUP_AUTHORIZED = 'pickup_authorized';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INVITED = 'invited';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_AMBIGUOUS = 'ambiguous';

    public const CONTACT_EMAIL = 'email';
    public const CONTACT_PHONE = 'phone';
    public const CONTACT_IN_APP = 'in_app';
    public const CONTACT_POSTAL = 'postal';

    protected $fillable = [
        'club_id',
        'member_user_id',
        'related_user_id',
        'related_email',
        'related_name',
        'relationship_type',
        'purposes',
        'contact_methods',
        'status',
        'is_primary',
        'valid_from',
        'valid_until',
        'accepted_at',
        'revoked_at',
        'created_by_user_id',
        'updated_by_user_id',
        'legacy_source',
        'legacy_source_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'purposes' => 'array',
            'contact_methods' => 'array',
            'is_primary' => 'boolean',
            'valid_from' => 'date:Y-m-d',
            'valid_until' => 'date:Y-m-d',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function member()
    {
        return $this->belongsTo(User::class, 'member_user_id');
    }

    public function relatedUser()
    {
        return $this->belongsTo(User::class, 'related_user_id');
    }
}
