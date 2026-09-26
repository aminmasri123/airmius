<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubMemberQualification extends Model
{
    use HasFactory;

    public const TYPES = ['qualification', 'training', 'license', 'certificate'];

    public const PROOF_STATUSES = ['not_required', 'missing', 'submitted', 'verified', 'rejected'];

    public const VISIBILITIES = ['membership_admins', 'member'];

    protected $fillable = [
        'club_id',
        'user_id',
        'club_external_member_id',
        'created_by',
        'type',
        'title',
        'issuer',
        'license_number',
        'valid_from',
        'valid_until',
        'proof_status',
        'proof_checked_at',
        'proof_checked_by',
        'remind_on',
        'is_sensitive',
        'visibility',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date:Y-m-d',
            'valid_until' => 'date:Y-m-d',
            'proof_checked_at' => 'datetime',
            'remind_on' => 'date:Y-m-d',
            'is_sensitive' => 'boolean',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function externalMember()
    {
        return $this->belongsTo(ClubExternalMember::class, 'club_external_member_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function proofCheckedBy()
    {
        return $this->belongsTo(User::class, 'proof_checked_by');
    }
}
